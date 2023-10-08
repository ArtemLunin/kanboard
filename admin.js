'use strict';

const requestURLTemplate = 'mop_admin.php',
    requestURLRender = 'mop_render.php',
    inputSelectorClass = 'input-edit';

let gPrimeElementID = 0,
    gActivityID = 0,
    adminEnabled = 1,
    totalInputs = 0,
    changedInputs = 0;

// const inputsData = [];

window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(document.location.search);
    const adminPage = parseInt(params.get("admin"));
    adminEnabled = adminPage;

    const formReset = document.querySelector('#formReset'),
        formSubmit = document.querySelector('#formSubmit'),
        formAdmin = document.querySelector('#formAdmin'),
        formFields = document.querySelector('#formFields'),
        adminViewElems = document.querySelectorAll('.admin-view'),
        btnNewPrimeElem = document.querySelector('#btnNewPrimeElem'),
        btnEditPrimeElem = document.querySelector('#btnEditPrimeElem'),
        btnDelPrimeElement = document.querySelector('#btnDelPrimeElem'),
        btnNewActivity = document.querySelector('#btnNewActivity'),
        btnEditActivity = document.querySelector('#btnEditActivity'),
        btnDelActivity = document.querySelector('#btnDelActivity'),
        newPrimeElem = document.querySelector('#newPrimeElement'),
        newActivity = document.querySelector('#newActivity'),
        selActivity = document.querySelector('#activity'),
        selPrimeElement = document.querySelector('#primeElement'),
        btnCeilAreaAppend = document.querySelector('#ceil_area_append'),
        divCounter = document.querySelector('.counter-pb'),
        showAll = document.querySelector('#showAll');

        showAll.checked = false;

        if (adminEnabled) {
            adminViewElems.forEach((elem) => {
                elem.classList.remove('hidden');
                elem.disabled = false;
            });
            formSubmit.innerText = 'Apply';
            document.querySelector('h1').innerText = 'Method of Procedure (MOP) Admin form';
        } else {
            adminViewElems.forEach((elem) => {
                elem.classList.add('hidden');
                elem.disabled = true;
            });
            formSubmit.innerText = 'Create';
            document.querySelector('h1').innerText = 'Method of Procedure (MOP) form';
        }
       

        btnNewActivity.dataset.prime_elem_id = 0;

        async function sendRequest(method, url, body) {
            const headers = {
                'Content-Type': 'application/json'
            };
            try {
                const response = await fetch(url, {
                    method: method,
                    body: JSON.stringify(body),
                    headers: headers
                });
                const data = await response.json();
                return data;
            } catch (e) {
                console.error(e);
            }
        };

        async function sendFormData(url, formData) {
            try {
                const data = await fetch(
                    url,
                    {
                        method: 'POST',
                        body: formData,
                    },
                );
                // const data = await response.json();
                const element = document.createElement('a');
                element.style.display = 'none';                    
                // Attach the content to the anchor
                element.setAttribute('href', 'application/vnd.ms-word; charset=utf-8,' + encodeURIComponent(data));
                element.setAttribute('download', 'example.docx');
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
            } catch (e) {
                console.error(e);
            }
        };

        const showOGPA = (data, extends_data) => {
            selPrimeElement.textContent = '';
            if (data && data.success && data.success.answer) {
                data.success.answer.forEach(item => {
                    let selected = '';
                    if (extends_data === item.element) {
                        selected = 'selected';
                    }
                    selPrimeElement.insertAdjacentHTML('beforeend', `
                        <option data-id="${item.id}" value="${item.element}" ${selected}>${item.element}</option>
                    `);
                }); 
            } else {
                selPrimeElement.insertAdjacentHTML('beforeend', `
                    <option value="" selected></option>
                `);
            }
            selPrimeElement.dispatchEvent(new Event('change'));
        };

        const showOGPAActivity = (data, extends_data) => {
            selActivity.textContent = '';
            if (data && data.success && data.success.answer) {
                data.success.answer.forEach(item => {
                    let selected = '';
                    if (extends_data === item.element) {
                        selected = 'selected';
                    }
                    selActivity.insertAdjacentHTML('beforeend', `
                        <option data-id="${item.id}" value="${item.element}" ${selected}>${item.element}</option>
                    `);
                }); 
            } else {
                selActivity.insertAdjacentHTML('beforeend', `
                    <option value="" selected></option>
                `);
            }
            selActivity.dispatchEvent(new Event('change'));
        };

        const showActivityFields = (data) => {
            formFields.reset();
            if (adminEnabled) {
                document.querySelectorAll('fieldset.hidden').forEach(item => {
                    item.classList.remove('hidden');
                });
                document.querySelectorAll('.renderOnly').forEach(item => {
                    item.disabled = true;
                    item.classList.add('hidden');
                });
            }
            if (data && data.success && data.success.answer) {
                data.success.answer.forEach(({groupID, hidden, fields}) => {
                    try {
                        const group = document.querySelector(`#${groupID}`);
                        try {
                            const checkbox = group.querySelector("input[type='checkbox']");
                            checkbox.checked = hidden ? false : true;
                            checkbox.dataset.ini_data = checkbox.checked;
                        } catch (e) {
                        }
                        if (group) {
                            fields.forEach(field => {
                                const fieldIn = document.querySelector(`#${field.fieidID}`);
                            if (field) {
                                fieldIn.value = field.default;
                                fieldIn.classList.add(inputSelectorClass);
                            }
                            });
                            if (adminEnabled || !hidden) {
                                group.classList.remove('hidden');
                            } else {
                                group.classList.add('hidden');
                            }
                        }
                    } catch (e) {
                    }
                });
                checkInputsData(`.${inputSelectorClass}`);
            }
        };

        // const refresh
        const iniOGPA = (extends_data = '') => {
            const body = {
                method: 'getOGPA',
            };
            sendRequest('POST', requestURLTemplate, body).then((data) => {
                showOGPA(data, extends_data);
                // selPrimeElement.dispatchEvent(new Event('change'));
            });
        };
        const iniOGPAActivity = (primeElemID, extends_data = '') => {
            const body = {
                method: 'getOGPAActivity',
                value: primeElemID
            };
            sendRequest('POST', requestURLTemplate, body).then((data) => {
                showOGPAActivity(data, extends_data);
            });
        };
        const getActivityFields = (activityID) => {
            const body = {
                method: 'getActivityFields',
                id: activityID
            };
            sendRequest('POST', requestURLTemplate, body).then((data) => {
                showActivityFields(data);
            });
        };

        const delElement = (method, callback, value) => {
            const body = {
                method: method,
                value: value
            };
            sendRequest('POST', requestURLTemplate, body).then(callback);
        };

        const checkTextValue = (value) => {
            return !(value == undefined || value.trim() == '');
        };

        const chgBtnType = (elem, arrayProps) => {
            for (const [key, value] of Object.entries(arrayProps)) {
                if (typeof value === "object") {
                    for (const [key_, value_] of Object.entries(value)) {
                        elem[key][key_] = value_;
                    }                    
                } else {
                    elem[key] = value;
                }
            }
        };

        const switchToNew = (elem, editableName = '') => {
            elem.classList.remove('pressed');
            const elem_name_id = `#${elem.dataset.elem_name_id}`,
                btn_new_id = `#${elem.dataset.btn_new_id}`;
            
            document.querySelector(elem_name_id).value = editableName;
            chgBtnType(document.querySelector(btn_new_id), {
                dataset: {
                    'type': 'new',
                },
                'textContent': 'Add new',
            });
        };

        const switchToMod = (elem, editableName) => {
            elem.classList.add('pressed');
            const elem_name_id = `#${elem.dataset.elem_name_id}`,
                btn_new_id = `#${elem.dataset.btn_new_id}`;
            
            document.querySelector(elem_name_id).value = editableName;
            chgBtnType(document.querySelector(btn_new_id), {
                dataset: {
                    'type': 'mod',
                },
                'textContent': 'Change',
            });
        };

        const switchBtnMode = ({elem, newMode, editableName, btnType, btnText}) => {
            if (newMode === 1) {
                elem.classList.remove('pressed');
            } else {
                elem.classList.add('pressed');
            }
            const elem_name_id = `#${elem.dataset.elem_name_id}`,
                btn_new_id = `#${elem.dataset.btn_new_id}`;
            
            document.querySelector(elem_name_id).value = editableName;
            chgBtnType(document.querySelector(btn_new_id), {
                dataset: {
                    'type': btnType,
                },
                'textContent': btnText,
            });
        };

        const submitAdminForm = (adminForm, activityID) => {
            let fieldGroups = [];
            adminForm.querySelectorAll('fieldset:not(.renderOnly)').forEach(fieldset => {
                let fieldGroup = {};
                fieldGroup.groupID = fieldset.id;
                fieldGroup.hidden = 0;
                let fieldsArr = [];
                for (let fieldEl of fieldset.elements) {
                    if (fieldEl.tagName === 'INPUT' || 
                        fieldEl.tagName === 'SELECT' || 
                        fieldEl.tagName === 'TEXTAREA') {
                        let fieldValue = fieldEl.value;
                        if (fieldEl.type === 'checkbox') {
                            fieldGroup.hidden = fieldEl.checked ? 0 : 1;
                            continue;
                        }
                        fieldsArr.push({
                            "fieidID": fieldEl.id,
                            "default": fieldValue,
                        });
                    }
                }
                fieldGroup.fields = fieldsArr;
                fieldGroups.push(fieldGroup);
            });
            const body = {
                method: 'setActivityFields',
                value: fieldGroups,
                id: activityID,
            };
            sendRequest('POST', requestURLTemplate, body).then((data) => {
                showActivityFields(data);
            });
        };

        const submitRenderForm = (renderForm) => {
            formAdmin.querySelectorAll('.renderData').forEach(item => {
                const added_input = document.createElement("input");
                added_input.name = item.dataset['value_name'];
                added_input.value = item.value;
                added_input.type = "hidden";
                renderForm.append(added_input);
            });
            const rows_object = {}
            renderForm.querySelectorAll("[data-parent='self']").forEach(item => {
                const rows_arr = [];
                item.closest('fieldset').querySelectorAll('.multirows').forEach(row_inputs => {
                    const one_row = {};
                    row_inputs.querySelectorAll('input').forEach(ceil_input => {
                        one_row[ceil_input.dataset['name']] = ceil_input.value;
                    });
                    rows_arr.push(one_row);
                });
                const item_name = item.dataset.id;
                rows_object[item_name] = rows_arr;
                item.closest('fieldset').querySelector(`#${item_name}`).value = JSON.stringify(rows_object[item_name]);
            });
            renderForm.submit();
        };

        const checkInputsData = (inputsSelector, setIni = true) => {
            totalInputs = 0;
            changedInputs = 0;
            document.querySelectorAll(inputsSelector).forEach(item => {
                if (item.type !== 'hidden' && item.type !== 'file') {
                    totalInputs++;
                    if (setIni) {
                        item.dataset.ini_data = item.value.trim().substring(0, 20)
                    }
                    if (item.value.trim() !== '') {
                        changedInputs++;
                    }
                }
            });
            const progress = Math.trunc((changedInputs / totalInputs) * 100);
            divCounter.style.width = `${progress}%`;
            divCounter.innerText = `${progress}% (${changedInputs} of ${totalInputs})`;
        };

        formAdmin.addEventListener('reset', (e) => {
            const target = e.target;
        });

        formFields.addEventListener('reset', (e) => {
            const target = e.target;
            formSubmit.classList.remove('edit');
        });

        formFields.addEventListener('submit', (e) => {
            e.preventDefault();
            const target = e.target;
            if (adminEnabled) {
                submitAdminForm(target, gActivityID);
            } else {
                submitRenderForm(target);
            }
        });

        formFields.addEventListener('focusout', (e) => {
            e.preventDefault();
            const target = e.target;
            if (target.classList.contains(inputSelectorClass)) {
                if (target.value.trim().substring(0, 20) != target.dataset.ini_data.trim().substring(0, 20))
                {
                    formSubmit.classList.add('edit');
                    checkInputsData(`.${inputSelectorClass}`, false);
                }
            }
        });

        formFields.addEventListener('click', (e) => {
            const target = e.target;
            if (target.type ==="checkbox" && target.dataset.ini_data !== target.checked) {
                formSubmit.classList.add('edit');
            }
        });

        btnNewPrimeElem.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target;
            if (!checkTextValue(newPrimeElem.value)) {
                return false;
            }
            
            let methodName = 'addPrimeElement';
            let id = 0;
            if (target.dataset['type'] === 'mod') {
                methodName = 'modPrimeElement';
                id = target.dataset.prime_elem_id;
            }

            const body = {
                method: methodName,
                value: newPrimeElem.value,
                id: id,
            };
            sendRequest('POST', requestURLTemplate, body).then((data) => {
                if (data && data.success && data.success.answer) {
                    showOGPA(data, newPrimeElem.value);
                    newPrimeElem.value = '';
                }
            });
        });

        btnEditPrimeElem.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target.closest('button');
            if (target.classList.contains('pressed')) {
                switchToNew(target, '');
            } else {
                switchToMod(target, selPrimeElement.value);
            }
        });

        btnEditActivity.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target.closest('button');
            if (target.classList.contains('pressed')) {
                switchBtnMode({
                    elem: target,
                    newMode: 1,
                    editableName: '', 
                    btnType: 'new', 
                    btnText: 'Add new'});
            } else {
                switchBtnMode({
                    elem: target,
                    newMode: 0,
                    editableName: selActivity.value, 
                    btnType: 'mod', 
                    btnText: 'Change'});
            }
        });

        btnNewActivity.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target;
            if (!checkTextValue(newActivity.value)) {
                return false;
            }

            let methodName = 'addActivity';
            let id = 0;
            if (target.dataset['type'] === 'mod') {
                methodName = 'modActivity';
                id = target.dataset.id;
            }

            const body = {
                method: methodName,
                value: newActivity.value,
                id: id,
                parentId: target.dataset.prime_elem_id
            };
            sendRequest('POST', requestURLTemplate, body).then((data) => {
                if (data && data.success && data.success.answer) {
                    showOGPAActivity(data, newActivity.value);
                    newActivity.value = '';
                }
            });
        });

        selPrimeElement.addEventListener('change', (e) => {
            e.preventDefault();
            const target = e.target;
            try {
                const id = target.options[target.selectedIndex].dataset.id;
                btnNewActivity.dataset.prime_elem_id = id;
                btnNewPrimeElem.dataset.prime_elem_id = id;
                gPrimeElementID = id;
                switchToNew(btnEditPrimeElem);
                iniOGPAActivity(id);
            } catch (e) {
                showActivityFields(null);
            }
        });

        selActivity.addEventListener('change', (e) => {
            e.preventDefault();
            const target = e.target;
            let id = 0;
            try {
                id = target.options[target.selectedIndex].dataset.id;
                btnNewActivity.dataset.id = id;
            } catch (e) {
            }
            gActivityID = id;
            newActivity.value = '';
            switchBtnMode({
                elem: btnEditActivity,
                newMode: 1,
                editableName: '', 
                btnType: 'new', 
                btnText: 'Add new'});
            getActivityFields(id);
        });

        btnDelPrimeElement.addEventListener('click', (e) => {
            e.preventDefault();
            delElement('delPrimeElement', showOGPA, selPrimeElement.value);
        });

        btnDelActivity.addEventListener('click', (e) => {
            e.preventDefault();
            const id = selActivity.options[activity.selectedIndex].dataset.id;
            delElement('delActivity', showOGPAActivity, id);
        });

        showAll.addEventListener('click', (e) => {
            if (e.target.checked) {
                document.querySelectorAll('fieldset.hidden').forEach(item => {
                    item.classList.remove('hidden');
                    item.classList.add('showned');
                });
            } else {
                document.querySelectorAll('fieldset.showned').forEach(item => {
                    item.classList.add('hidden');
                    item.classList.remove('showned');
                });
            }
        });

        btnCeilAreaAppend.addEventListener('click', (e) => {
            e.preventDefault();
            const fieldset = e.target.closest('fieldset');
            const row = fieldset.querySelector("[data-parent='self']");
            const row_prime = fieldset.querySelector('.multirows');
            const new_row = row_prime.cloneNode(true);
            row.value = parseInt(row.value) + 1;
            new_row.querySelectorAll('input').forEach(item => {
                item.name = `${item.dataset.name}_${row.value}`;
                item.id = item.name;
                item.value = '';
            });
            fieldset.append(new_row);
            fieldset.append(e.target);
        });

        iniOGPA();
        
});