'use strict';

const requestURL = 'backend.php';

const entityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
};

const sections = [
	'user',
	'main',
	'status',
	'excel',
	'statistics',
	'action',
];

const defaultUserRights = [];

sections.forEach((item, idx) => {
	defaultUserRights[idx] = `<td data-${item}>&nbsp</td>`;
});

const textCreatorHeader = 'Submitted by:';
const textOTLHeader = 'OTL:';

let fileAttach;
let pageStatus = 0, fileDeleted = 0, totalWaits = 0, periodDays = 0;
let currentUser = '';
let previousElem = null;

let dataTableObj;

const errorMsg = document.createElement('div');
errorMsg.textContent = 'Username or password is incorrect';

// common functions
// for sort in ORDER DESC
const byField = (field) => {
	return (a, b) => a[field] > b[field] ? -1 : 1;
};

const timestampToDate = (timestampValue, timeOut = true) => {
	if(!timestampValue) {
		return '';
	}
  const a = new Date(timestampValue * 1000);
  const months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
  let dateOut = `${a.getFullYear()}-${months[a.getMonth()]}-${addZero(a.getDate())}`;
  if(timeOut) {
	dateOut = `${dateOut} ${addZero(a.getHours())}:${addZero(a.getMinutes())}:${addZero(a.getSeconds())}`;
  }
  return dateOut;
}

const timestampToTime = (timestampValue) => {
	if(!timestampValue) {
		return '';
	}
	const a = new Date(timestampValue * 1000);
	return `${addZero(a.getHours())}:${addZero(a.getMinutes())}`;
};

const dateToTimestamp = date_str => {
	const date = new Date(date_str);
	const ts = date.valueOf() / 1000;
	if (!isNaN(ts)) {
		return ts;
	}
	return 0;
};

// const tsPeriod = () => {
// 	const today = new Date();
// 	const nextDay = new Date();
// 	nextDay.setDate(nextDay.getDate() + periodDays - 1);
// 	let dayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
// 	if (periodDays === 0) {
// 		dayStart -= 24 * 3600 * 1000;
// 	}
// 	const dayEnd = new Date(nextDay.getFullYear(), nextDay.getMonth(), nextDay.getDate(), 23, 59, 59);
// 	return {
// 		dayStart: dayStart.valueOf() / 1000,
// 		dayEnd: dayEnd.valueOf() / 1000,
// 	}
// };

// const hideTask = (date_due) => {
// 	let {dayStart, dayEnd} = tsPeriod();
// 	let hideTaskClass = 'd-none';

// 	if(!date_due || (date_due > dayStart && date_due < dayEnd)) {
// 		hideTaskClass = '';
// 	}
// 	return hideTaskClass;
// };

const tsPeriod = () => {
	const today = new Date();
	const prevDay = new Date();
	prevDay.setDate(prevDay.getDate() - periodDays);
	if (periodDays < 0)
	{
		today.setDate(today.getDate() - periodDays);
	}
	let dayStart = new Date(prevDay.getFullYear(), prevDay.getMonth(), prevDay.getDate(), 0, 0, 0);
	let dayEnd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
	return {
		dayStart: dayStart.valueOf() / 1000,
		dayEnd: dayEnd.valueOf() / 1000,
	}
};

const hideTask = (date_started) => {
	let {dayStart, dayEnd} = tsPeriod();
	let hideTaskClass = 'd-none';

	if(!date_started || (date_started > dayStart && date_started < dayEnd)) {
		hideTaskClass = '';
	}
	return hideTaskClass;
};

const cr2spaces = data_str => {
	return String(data_str).replace(/\n/g, '  ');
};

const spaces2cr = data_str => {
	return String(data_str).replace(/\s\s/g, '\n');
};

const addZero = i => {
	if(i < 10) {
    i = "0" + i;
  }
  return i;
};

const getField = (fieldsObj, fieldName, defaultValue) => {
	let fieldValue = defaultValue;
	if (!!fieldsObj[fieldName]) {
		fieldValue = fieldsObj[fieldName];
	}
	return fieldValue;
};

const escapeHTML = (string) => {
	return String(string).replace(/[&<>"'`=\/]/g, (s) => entityMap[s]);
};

const capitalize = (string) => string.charAt(0).toUpperCase() + string.slice(1);

window.addEventListener('DOMContentLoaded', () => {
	const menu = document.querySelector('.menu ul'),
		section = document.querySelectorAll('.section');
	const loginForm = document.querySelector('#login-form'),
		newUserForm = document.querySelector('#new-user-form'),
		btnAddUser = document.querySelector('#btnAddUser'),
		newUsernameInput = document.querySelector('#newUsername'),
		newPasswordInput = document.querySelector('#newPassword'),
		rightsUserName = document.querySelector('#rightsUserName'),
		rightsForm = document.querySelector('#rights-form');
	const formsAuth = document.querySelectorAll('.form-auth');
	// main elements
	const ticketsContainer = document.querySelector('.tickets-container'),
		btnUpdateTask = document.querySelector('.btn-update-task'),
		btnAddTask = document.querySelector('.btn-add-task'),
		btnCreateTaskFile = document.querySelector('#attachFile'),
		ticketProjectName = document.querySelector('#inputProject'),
		ticketTitle = document.querySelector('.ticket-title'),
		ticketCreator = document.querySelector('.ticket-creator'),
		ticketDescription = document.querySelector('.ticket-description'),
		ticketOTL = document.querySelector('.ticket-OTL'),
		attachmentsContainer = document.querySelector('.attachments-container'),
		attachmentsArea = document.querySelector('.attachments-area'),
		btnClearSettings = document.querySelector('.btn-clear-settings'),
		formNewTask = document.querySelector('#formNewTask'),
		mainForm = document.querySelector('#mainForm'),
		containerError = document.querySelector('.container-error'),
		usersList = document.querySelector('.users-list'),
		tableUsers = document.querySelector('.table-users'),
		taskMain_id = document.querySelector('#task_id'),
		setRightsContainer = document.querySelector('.set-rights');
	// excel elements
	const btnUpdateTicket = document.querySelector('.btn-update-ticket'),
		periodSelect = document.querySelector('.period-select'),
		tableExcel = document.querySelector('.table-excel'),
		btnRemove = document.querySelector('.btn-remove'),
		inputName = document.querySelector('#inputName'),
		inputDate = document.querySelector('#inputDate'),
		inputTime = document.querySelector('#inputTime'),
		// inputDescr = document.querySelector('#inputDescr'),
		inputTitle = document.querySelector('#inputTitle'),
		inputTicket = document.querySelector('#inputTicket'),
		inputCapOp = document.querySelector('#inputCapOp'),
		inputOracle = document.querySelector('#inputOracle'),
		ticketEditForm = document.querySelector('#ticketEditForm'),
		ticketTitleExcel = document.querySelector('.ticket-title-excel'),
		// ticketDescr = document.querySelector('.ticket-descr'),
		exportExcel = document.querySelector('#exportExcel');
	// status elements
		const formNewTaskStatus = document.querySelector('#formNewTaskStatus'),
			titleStatus = document.querySelector('#titleStatus'),
			btnUpdateTaskStatus = document.querySelector('.btn-update-task-status'),
			btnAddTaskStatus = document.querySelector('.btn-add-task-status'),
			ticketDescriptionStatus = document.querySelector('#ticketDescriptionStatus'),
			btnCreateTaskFileStatus = document.querySelector('#attachFileStatus'),
			attachmentsContainerStatus = document.querySelector('.attachments-container-status'),
			ticketProjectNameStatus = document.querySelector('#inputProjectStatus'),
			taskStatus_id = document.querySelector('#taskStatus_id'),
			origin_id = document.querySelector('#origin_id'),
			attachmentsAreaStatus = document.querySelector('.attachments-area-status'),
			tableStatus = document.querySelector('.table-status'),
			ticketCreatorStatus = document.querySelector('#creatorStatus'),
			ticketOTLStatus = document.querySelector('#OTLStatus');
		// statistics elements
		const tableStatistics = document.querySelector('.table-statistics'),
			exportStatistics = document.querySelector('#exportStatistics');
	btnUpdateTicket.disabled = true;
	btnUpdateTicket.dataset['task_id'] = 0;
	
	async function sendRequest(method, url, body, showWait = false) {
		const headers = {
			'Content-Type': 'application/json'
		};
		if(showWait) {
		// 	// totalWaits++;
		// 	// setTimeout(() => {
				$('#waitModal').modal('show');
		// 	// }, 500);
		}
		try {
			const response = await fetch(url, {
				method: method,
				body: JSON.stringify(body),
				headers: headers
			});
			const data = await response.json();
			// totalWaits--;
			// if (showWait && totalWaits == 0)
			// {
				// $('#waitModal').modal('hide');
			// }
			return data;
		} catch (e) {
			console.error(e);
			// if(showWait) {
				$('#waitModal').modal('hide');
			// }
		}
	}

	async function sendFile(method, url, body ) {
		try {
			const response = await fetch(url, {
				method: method,
				body: body,
			});
			const data = await response.json();
			return data;
		} catch (e) {
			console.error(e);
		}
	}

	const toggleSignIn = (mode) => {
		clearInputsForms();
		errorMsg.remove();
		$('#login-dialog').modal(mode);
	};

	const iniInterface = (useSignIn = false) => {
		currentUser = '';
		clearEditableFields();
		clearAllSection('data-showned');
		let body = {
			method: 'getRights',
		};
		if (useSignIn) {
			body = {
				method: 'signin',
			}
		}
		sendRequest('POST', requestURL, body).then(showInterface);
	};

	const logout = () => {
		const body = {
			method: 'logout',
		};
		sendRequest('POST', requestURL, body).then(() => {
			iniInterface(false);
		});
	};

	const toggleSection = (showSection = null) => {
		let idx = 0;
		section.forEach((item, i) => {
			item.style.display = 'none';
			if (showSection && item.classList.contains(showSection)) {
				idx = i;
			}
		});
		section[idx].style.display = 'block';
		switch (showSection) {
			case 'main':
				if (!section[idx].dataset['showned']) {
					// section[idx].dataset['showned'] = '1';
					getAllTask();
				}
				break;
			case 'status':
				if (!section[idx].dataset['showned']) {
					// section[idx].dataset['showned'] = '1';
					getTaskStatus();
				}
				break;
			case 'statistics':
				if (!section[idx].dataset['showned']) {
					// section[idx].dataset['showned'] = '1';
					getTaskStatistics();
				}
				break;
			case 'excel':
				if (!section[idx].dataset['showned']) {
					// section[idx].dataset['showned'] = '1';
					getTaskBoard();
				}
				break;
			default:
				break;
			case 'settings':
				if (!section[idx].dataset['showned']) {
					section[idx].dataset['showned'] = '1';
					getKanboardUsers();
				}
				break;
		}
	};

	menu.addEventListener('click', (e) => {
		const target = e.target;
		if (target.tagName === 'LI') {
			if (target.dataset['section'] === 'logout') {
				logout();
			} else if (target.dataset['section'] === 'login') {
				toggleSignIn('show');
			} else {
				Array.from(target.parentNode.children).forEach(item => {
					item.style.backgroundColor = '';
				});
				target.style.backgroundColor = 'rgba(0,0,0,0.1)';
				toggleSection(target.dataset['section']);
			}
		}
	});

	const showInterface = (data) => {
		let loginAction = 'logout';
		menu.textContent = '';
		if(!!data.success) {
			if (data.success.answer.user === 'defaultUser') {
				loginAction = 'login'
			} else {
				currentUser = data.success.answer.user;
			}
			data.success.answer.rights.forEach(({pageName, sectionAttr, sectionName, accessType}) => {
				if (accessType != '') {			
					menu.insertAdjacentHTML('beforeend', `
					<li data-section="${sectionAttr}">${pageName}</li>
					`);
					if (sectionName === 'excel')
					{
						if (accessType === 'user')
						{
							toggleNoAccessRights('period-select', 'user-none', 'none');
						} else {
							toggleNoAccessRights('period-select', 'user-none', '');
						}
					}
				}
			});
		}
		menu.insertAdjacentHTML('beforeend', `
			<li data-section="${loginAction}">${capitalize(loginAction)}</li>
		`);
		$('#waitModal').modal('hide');
		menu.children[0].style.backgroundColor = 'rgba(0,0,0,0.1)';
		toggleSection('main');
	};

	const toggleNoAccessRights = (selector, elem, displayProp) => {
		try {
			const elemsToggle = document.querySelectorAll(`.${selector} .${elem}`);
			elemsToggle.forEach(item => {
				item.style.display = displayProp;
			});
		} catch (e) {}
	};

	const checkSignIn = (data) => {
		if(!!data.success && data.success.answer) {
			if (data.success.answer.rights) {
				toggleSignIn('hide');
				showInterface(data);
			} else {
				loginForm.append(errorMsg);
			}
		}
	};

	const toggleToUpdateMode = (btnUpdate, btnAdd, attachments) => {
		btnUpdate.classList.remove('d-none');
		btnAdd.classList.add('d-none');
		attachments.classList.remove('invisible');
	};

	const apiCallbackProps = {
		'getTagsByProject': function (data, container) {
			fillProjectsList(data, container);
		},
	};

	const fillFileInfo = (fileInfo, attachmentsList) => {
		let { file_id, file_name, file_size } = JSON.parse(fileInfo);
		attachmentsList.insertAdjacentHTML('beforeend', `
			<p class="file-delete-container">File name: ${file_name}, Size: ${file_size}
				<img class="file-delete" src="img/delete.svg" atl="Delete file" data-file_id="${file_id}" title="Delete file"/>
			</p>
		`);
	};

	const signIn = ({method = 'signIn', userName, password}) => {
		const body = {
			method: method,
			params: {
				'userName': userName,
				'password': password,
			}
		};
		if (method === 'signIn') {
			sendRequest('POST', requestURL, body).then(checkSignIn);	
		} else if (method === 'addUser' || method === 'modUser') {
			sendRequest('POST', requestURL, body).then(showAddedUser);	
		}
	};

	const modUser = (method, userName, password = '') => {
		const body = {
			method: method,
			params: {
				'userName': userName,
				'password': password,
			}
		};
		sendRequest('POST', requestURL, body).then(getKanboardUsers);
	};

	const modRights = (userName, newRights) => {
		const body = {
			method: 'setRights',
			params: {
				'userName': userName,
				'rights': newRights,
			}
		};
		sendRequest('POST', requestURL, body).then(showModifiedRights);
	};

	const createTask = (callback) => {
		if (ticketTitle.value.trim().length == 0 || ticketCreator.value.trim().length == 0 || ticketProjectName.value == '' || ticketDescription.innerText.trim().length == 0)
		{
			return false;
		}
		const body = {
			method: 'createTask',
			params: {
				title: ticketTitle.value,
				description: ticketDescription.innerText,
				creator: ticketCreator.value,
				OTL: ticketOTL.value,
				projectName: ticketProjectName.value,
				section: mainForm.value,
			},
		};
		sendRequest('POST', requestURL, body).then(callback);
	};

	const updateTask = (e) => {
		const body = {
			method: 'updateTask',
			params: {
				title: ticketTitle.value,
				description: ticketDescription.innerText,
				creator:ticketCreator.value,
				OTL: ticketOTL.value,
				projectName: ticketProjectName.value,
				id: btnUpdateTask.dataset['task_id'],
				section: mainForm.value,
			},
		};
		sendRequest('POST', requestURL, body).then(showUpdatedTask);
	};

	const getAllTask = () => {
		const body = {
			method: 'getAllTasks',
		}
		sendRequest('POST', requestURL, body, true).then(showAllTasks);
		ticketCreator.value = currentUser;
	};


	const getAllTaskFiles = (taskID, attachmentsList) => {
		const body = {
			method: 'getAllTaskFiles',
			params: {
				id: taskID,
			},
		}
		sendRequest('POST', requestURL, body).then((data) => {
			showAddedFileNew(data, attachmentsList);
		});	
	};

	const getBoard = (action = 0) => {
		const body = {
			method: 'getBoard',
			params: {
				status: 'all',
				section: action,
			},
		}
		if (action == 'status') {
		 	sendRequest('POST', requestURL, body, true).then(showStatusTable);
		} else if (action == 'statistics') {
			sendRequest('POST', requestURL, body, true).then(showStatisticsTable);
		} else {
			sendRequest('POST', requestURL, body, true).then(showBoardTable);
		}
	};

	const getUsers = () => {
		const body = {
			method: 'getAssignableUsers',
		}
		sendRequest('POST', requestURL, body).then(fillUsersList);
	};

	const getKanboardUsers = () => {
		const body = {
			method: 'getKanboardUsers',
		}
		sendRequest('POST', requestURL, body).then(showUsers);
	}

	const getDataFromKanboard = (apiName, apiProps, container) => {
		const body = {
			method: apiName,
		}
		sendRequest('POST', requestURL, body).then((data) => {
			apiProps[apiName](data, container);
		});
	};

	const fillProjectsList = (data, elemProjectList) => {
		if(!!data.success) {
			elemProjectList.innerHTML = '<option value="" selected disabled hidden>Choose project</option>';
			data.success.answer.forEach(function ({project_name}) {
				elemProjectList.insertAdjacentHTML('beforeend', `
					<option value="${project_name}">${project_name}</option>
				`);
			});
		}
		elemProjectList.value = '';
	};

	const fillUsersList = (data) => {
		// if(!!data.success) {
		// 	inputName.textContent = '';
		// 	data.success.answer.forEach(function ({user_name}) {
		// 		inputName.insertAdjacentHTML('beforeend', `
		// 			<option value="${user_name}">${user_name}</option>
		// 		`);
		// 	});
		// }
		// inputName.value = '';
	};

	const editExcelTask = (e) => {
		e.preventDefault();
		const target = e.target;
		if(target.classList.contains('icon-edit')) {
			const taskTicket = target.closest('.task-ticket-excel');
			if(!!taskTicket) {
				clearExcelTicketFields();
				selectTR('.task-ticket-excel', taskTicket);
				const taskID = taskTicket.dataset['task_id'];
				for(const item of taskTicket.children)
				{
					const itemValue = item.dataset['item_value'];
					const inputID = item.dataset['item_id'];
					if(!itemValue || !inputID) continue;
					try {
						document.querySelector(`#${inputID}`).value = itemValue;
					} catch (e) {}
				}
				btnUpdateTicket.dataset['task_id'] = taskID;
				btnUpdateTicket.disabled = false;
			}
		} else if(target.classList.contains('icon-delete')) {
			const taskTicket = target.closest('.task-ticket-excel');
			if(!!taskTicket) {
				const taskID = taskTicket.dataset['task_id'];
				btnRemove.dataset['task_id'] = taskID;
				ticketTitleExcel.textContent = taskTicket.querySelector('.ticket-title-table').dataset['item_value'];
				$('#modalRemoveDialog').modal('show');
			}
		}
	};

	const editStatusTask = (e) => {
		const target = e.target;
		if(target.classList.contains('icon-edit')) {
			if (previousElem === target) {
				return false;
			}
			formNewTaskStatus.reset();
			previousElem = target;
			ticketCreatorStatus.value = currentUser;
			const taskID = setFieldsEditForm(target, '.task-ticket-status', taskStatus_id);
			toggleToUpdateMode(btnUpdateTaskStatus, btnAddTaskStatus, attachmentsAreaStatus);
			if (taskID !== 0) {
				btnCreateTaskFileStatus.setAttribute('task_id', taskID);
				getAllTaskFiles(taskID, attachmentsContainerStatus);
			}
			// 
			const taskDescription = ticketDescriptionStatus.innerText;
			const positionCreator = taskDescription.lastIndexOf(textCreatorHeader);
			const positionOTL = taskDescription.lastIndexOf(textOTLHeader);
			let extDescriptionPosition = 0;
			let endPositionOTL, endPositionCreator = 0;

			if (positionOTL !== -1) {
				extDescriptionPosition = positionOTL;
			} else {
				ticketOTLStatus.value = '';
			}
			if (positionCreator !== -1) {
				if (!extDescriptionPosition || (extDescriptionPosition > positionCreator)) {
					extDescriptionPosition = positionCreator;
				}
			} else {
				ticketCreatorStatus.value = '';
			}
			if (extDescriptionPosition) {
				ticketDescriptionStatus.innerText = taskDescription.substring(0, extDescriptionPosition).trim();
				if (positionOTL < positionCreator) {
					endPositionOTL = positionCreator;
					endPositionCreator = taskDescription.length;
				} else {
					endPositionCreator = positionOTL;
					endPositionOTL = taskDescription.length;
				}
				if (positionOTL !== -1) {
					ticketOTLStatus.value = taskDescription.substring(positionOTL + textOTLHeader.length, endPositionOTL).trim();
				}
				if (positionCreator !== -1) {
					ticketCreatorStatus.value = taskDescription.substring(positionCreator + textCreatorHeader.length, endPositionCreator).trim();	
				}
			} else {
				ticketDescriptionStatus.innerText = taskDescription.trim();
			}
		}
	};

	const setFieldsEditForm = (targetElem, rowSelector, elemTaskID) => {
		let taskID = 0, originTaskID = 0;
		const rowTask = targetElem.closest(rowSelector);
		if(!!rowTask) {
			selectTR(rowSelector, rowTask);
			taskID = rowTask.getAttribute('data-task_id');
			originTaskID = rowTask.getAttribute('data-origin_id');
			for(const item of rowTask.children)
			{
				const itemValue = item.dataset['item_value'];
				const inputID = item.dataset['item_id'];
				if(!itemValue || !inputID) continue;
				const inputElem = document.querySelector(`#${inputID}`);
				if (!!inputElem) {
					if (inputElem.nodeName === 'DIV') {
						inputElem.innerText = itemValue;
					} else {
						inputElem.value = itemValue;
					}
					if (inputElem.getAttribute('data-disable_on_update') == '1') {
						inputElem.readOnly = true;
						inputElem.classList.add('text-muted');
					}
				}
			}
			elemTaskID.value = taskID;
			// origin_id.value = taskID;
			if (originTaskID && originTaskID != 0) {
				elemTaskID.value = originTaskID;
			}
		}
		return taskID;
	};

	const showAddedFileNew = (data, attachmentsList) => {
		if (data && data.success && data.success.answer.files) {
			const taskID = data.success.answer.id;
			fillFileTaskInfo(data.success.answer.files, taskID);
			attachmentsList.textContent = '';
			(data.success.answer.files).forEach(fileItem => fillFileInfo(JSON.stringify(fileItem), attachmentsList));
		}
	};

	const showAddedUser = (data) => {
		if (data.success.answer && (data.success.answer.length > 0 || Object.keys(data.success.answer).length > 0)) {
			getKanboardUsers();
		}
	};

	const removeFileFromList = (resultFileList) => {
		if (!!resultFileList.success.answer) {
			if (fileDeleted != 0) {
				fileDeleted.remove();
				fileDeleted = 0;
				const taskID = resultFileList.success.answer.id;
				fillFileTaskInfo(resultFileList.success.answer.files, taskID);
			}
		}
	};
	const filesAttached = (filesArray) => {
		if (filesArray.length) {
			const filesAttachedView = `
				<img class="files-list" src="img/attach_file-small.svg" alt="file attached" data-files_list="${escapeHTML(JSON.stringify(filesArray.map(file => JSON.stringify(file))))}"/>
				`;
			return filesAttachedView;
		} else {
			return '';
		}
	};

	const fillFileTaskInfo = (filesArray, taskID) => {
		try {
			const fileTaskInfo = document.querySelector('#task_id_' + taskID);
			fileTaskInfo.innerHTML = filesAttached(filesArray);
		}
		catch (e) {
		}
	};

	const actionTask = (event) => {
		const target = event.target;
		const hrefAction = target.closest('.task-ticket');
		if (!!hrefAction)
		{
			const taskTitle = hrefAction.querySelector('.task-title');
			const taskDescription = hrefAction.querySelector('.task-description');
			const taskProjectName = hrefAction.querySelector('.task-project-name');
			const taskID = hrefAction.dataset['task_id'];
			const positionCreator = taskDescription.innerText.lastIndexOf(textCreatorHeader);
			const positionOTL = taskDescription.innerText.lastIndexOf(textOTLHeader);
			const filesList = hrefAction.querySelector('.files-list');
			let extDescriptionPosition = 0;
			let endPositionOTL, endPositionCreator = 0;
			fileAttach = hrefAction.querySelector('.file-attach');
			if (positionOTL !== -1) {
				extDescriptionPosition = positionOTL;
			} else {
				ticketOTL.value = '';
			}
			if (positionCreator !== -1) {
				if (!extDescriptionPosition || (extDescriptionPosition > positionCreator)) {
					extDescriptionPosition = positionCreator;
				}
			} else {
				ticketCreator.value = '';
			}
			if (extDescriptionPosition) {
				ticketDescription.innerText = taskDescription.innerText.substring(0, extDescriptionPosition).trim();
				if (positionOTL < positionCreator) {
					endPositionOTL = positionCreator;
					endPositionCreator = taskDescription.innerText.length;
				} else {
					endPositionCreator = positionOTL;
					endPositionOTL = taskDescription.innerText.length;
				}
				if (positionOTL !== -1) {
					ticketOTL.value = taskDescription.innerText.substring(positionOTL + textOTLHeader.length, endPositionOTL).trim();
				}
				if (positionCreator !== -1) {
					ticketCreator.value = taskDescription.innerText.substring(positionCreator + textCreatorHeader.length, endPositionCreator).trim();	
				}
			} else {
				ticketDescription.innerText = taskDescription.innerText.trim();
			}
			attachmentsContainer.textContent = '';
			if (!!filesList) {
				JSON.parse(filesList.dataset['files_list']).forEach((item) => {
					fillFileInfo(item, attachmentsContainer);
				});
			}
			ticketTitle.value = taskTitle.textContent;
			ticketProjectName.value = taskProjectName.textContent;
			btnUpdateTask.dataset['task_id'] = taskID;
			taskMain_id.value  = taskID;
			toggleToUpdateMode(btnUpdateTask, btnAddTask, attachmentsArea);
		}
	};

	const clearEditableFields = () => {
		ticketTitle.value = '';
		ticketCreator.value = currentUser;
		ticketOTL.value = '';
		ticketProjectName.value = '';
		ticketDescription.textContent = '';
		btnUpdateTask.classList.add('d-none');
		btnAddTask.classList.remove('d-none');
		attachmentsArea.classList.add('invisible');
		btnUpdateTask.dataset['task_id'] = 0;
		taskMain_id.value = 0;
	};

	const clearAllSection = (shownedSections) => {
		document.querySelectorAll(`[${shownedSections}]`).forEach(item => {
			item.removeAttribute(shownedSections);
		});
		tableExcel.textContent = '';
		tableStatus.textContent = '';
	};

	const clearExcelTicketFields = () => {
		ticketEditForm.reset();
	};

	const selectTR = (selector, taskTicket) => {
		const taskTickets = document.querySelectorAll(selector);
		taskTickets.forEach(item => {
			item.classList.remove('table-primary');
		});
		taskTicket.classList.add('table-primary');
	};

	const periodChange = (e) => {
		const target = e.target;
		if(target.classList.contains('btn')) {
			for(const item of periodSelect.children)
			{
				item.classList.remove('btn-primary');
				item.classList.add('btn-secondary');
			}
			target.classList.remove('btn-secondary');
			target.classList.add('btn-primary');
			periodDays = parseInt(target.dataset['days'], 10);
			if(isNaN(periodDays) || periodDays > 365 || periodDays < -1) {
				periodDays = 0;
			}
			refreshBoardTable();
		}
	};

	const refreshBoardTable = () => {
		const taskTickets = document.querySelectorAll('.task-ticket-excel');
		taskTickets.forEach(item => {
			if (hideTask(parseInt(item.dataset['date_started'], 10)) === 'd-none') {
				item.classList.add('d-none');
			} else {
				item.classList.remove('d-none');
			}
		});
	};

	const createTaskFileNew = (e, attachmentsList) => {
		const target = e.target;
		const inputData = new FormData(target.form);
		let task_id = inputData.get('id');
		const formData = new FormData();
		const fileStatusTaskID = btnCreateTaskFileStatus.getAttribute('task_id');
		if (!!fileStatusTaskID) {
			task_id = fileStatusTaskID;
		}
		formData.append('file', target.files[0]);
		formData.append('method', 'createTaskFile');
		formData.append('id', task_id);
		sendFile('POST', requestURL, formData).then((data) => {
			showAddedFileNew(data, attachmentsList);
		});
	};

	const attachmentsAction = (event) => {
		const target = event.target;
		const fileAction = target.closest('.file-delete');
		if (!!fileAction)
		{
			fileDeleted = target.closest('.file-delete-container');
			const body = {
				method: 'removeTaskFile',
				params: {
					id: fileAction.dataset['file_id'],
				},
			}
			sendRequest('POST', requestURL, body).then((data) => {
				removeFileFromList(data);
			});
		}
	};

	const showModifiedRights = (data) => {
		getKanboardUsers();
	};

	const clearInputsForms = () => {
		formsAuth.forEach(form => {
			form.reset();
		});
	};

	const actionForUsers = (e) => {
		e.preventDefault();
		const target = e.target.closest('tr');
		const href = e.target.closest('a');
		target.querySelectorAll('a').forEach(item => {
			if (item == href) {
				const userName = target.dataset['username'];
				if (!!userName) {
					const action = href.dataset['action'];
					if (action === 'delUser') {
						modUser('delUser', userName);
					} else if (action === 'setRights') {
						target.childNodes.forEach(td => {
							if (td.nodeName === 'TD' && td.dataset['select']) {
								setSelectMode(td.dataset['mode'], td.dataset['select']); 
							}
						});
						newUsernameInput.value = userName;
						rightsUserName.value = userName;
						btnAddUser.textContent = 'Change password';
						newUserForm.dataset['method'] = 'modUser';
						newUsernameInput.readOnly = true;
						newPasswordInput.value = '';
						setRightsContainer.style.display = 'block';
					}
				}
				
			}
		});
		
	};

	const setSelectMode = (selectName, selectMode) => {
		const select = document.querySelector(`select[data-selectname="${selectName}"]`);
		if (!!select) {
			select.value = selectMode;
		}
	};

	const updateTaskFull = () => {
		if(btnUpdateTicket.dataset['task_id'] != 0) {
			const date_start = new Date(`${inputDate.value}T${inputTime.value}`);
			
			const body = {
				method: 'updateTaskFull',
				params: {
					// description: spaces2cr(inputDescr.value),
					title: inputTitle.value.trim(),
					id: btnUpdateTicket.dataset['task_id'],
					date_started: date_start.getTime() / 1000,
					reference: inputTicket.value.trim(),
					capop: inputCapOp.value.trim(),
					oracle: inputOracle.value.trim(),
					section: ticketEditForm.querySelector('#excelForm').value,
				},
			}
			sendRequest('POST', requestURL, body).then(getTaskBoard);
		}
	};

	const removeTask = () => {
		$('#modalRemoveDialog').modal('hide');
		const body = {
			method: 'removeTask',
			params: {
				id: btnRemove.dataset['task_id'],
				section: ticketEditForm.querySelector('#excelForm').value,
			},
		}
		sendRequest('POST', requestURL, body).then(removeTaskFull);
	};

	const removeTaskFull = (data) => {
		if (!!data.success) {
			const id = data.success.answer.id;
			if (!!id) {
				const tr_excel = tableExcel.querySelector(`tr[data-task_id="${id}"]`);
				if (tr_excel) {
					tr_excel.remove();
				}
			}
		}
	};

	const getTaskBoard = () => {
		clearExcelTicketFields();
		getBoard('excel');
	};

	const getTaskStatus = () => {
		formNewTaskStatus.reset();
		getDataFromKanboard('getTagsByProject', apiCallbackProps, ticketProjectNameStatus);
		getBoard('status');
		ticketCreatorStatus.value = currentUser;
	};

	const getTaskStatistics = () => {
		getBoard('statistics');
	};

	function showAddedTask(data)
	{
		attachmentsContainer.textContent = '';
		toggleToUpdateMode(btnUpdateTask, btnAddTask, attachmentsArea);
		if(data.success && data.success.answer) {
			let {id, date_creation, description, title, project_name, files = []} = data.success.answer;
			btnUpdateTask.dataset['task_id'] = data.success.answer.id;
			ticketsContainer.insertAdjacentHTML('afterbegin', `
					<div class="task-ticket" data-task_id="${id}">
						<a href="#" class="task-action" data-task_id="${id}">#${id}</a>
						<h6 class="task-title">${title}</h6>
						<p class="task-description">${description}</p>
						<p class="task-project">Project: <span class="task-project-name">${project_name}</span></p>
						<div class="task-footer">
							<span id="task_id_${id}" class="file-attach">${filesAttached(files)}</span>
							<span>${timestampToDate(date_creation)}</span>
						</div>
					</div>
				`);
		}
	}

	// function showAddedTaskStatus(data)
	// {
	// 	taskStatus_id.value = data.success.answer.id;
	// 	attachmentsContainerStatus.textContent = '';

	// 	toggleToUpdateMode(btnUpdateTaskStatus, btnAddTaskStatus, attachmentsAreaStatus);
	// 	if(!!data.success) {
	// 		let {id, title, assignee_name, status, date_creation, reference, description, project_name, fields} = data.success.answer;
	// 		let submitted_name = '&nbsp;';
	// 		if (!!fields['creator'] && fields['creator'] !== '') {
	// 			submitted_name = fields['creator'];
	// 		}
	// 		tableStatus.insertAdjacentHTML('afterbegin', `
	// 			<tr class="task-ticket-status" data-task_id="${id}">
	// 				<td>${id}</td>
	// 				<td data-item_value="${title}" data-item_id="titleStatus">${title}</td>
	// 				<td data-item_value="${submitted_name}" data-item_id="creatorStatus">${submitted_name}</td>
	// 				<td data-item_value="${getOTL(fields)}" data-item_id="OTLStatus">${assignee_name}</td>
	// 				<td>${timestampToDate(date_creation, false)}</td>
	// 				<td data-item_value="${description}" data-item_id="ticketDescriptionStatus">${reference}</td>
	// 				<td data-item_value="${project_name}" data-item_id="inputProjectStatus">${status}</td>
	// 				<td class="text-center">
	// 					<a href="#"><img class="icon-edit" src="img/edit.svg"></a>
	// 				</td>
	// 			</tr>			
	// 		`);
	// 	}
	// }

	function showUpdatedTask(data)
	{
		// $('#waitModal').modal('hide');
		clearEditableFields();
		if(!!data.success) {
			let {id, creator_id, date_completed, date_creation, description, title, project_name} = data.success.answer;
			const hrefAction = document.querySelector(`.task-ticket[data-task_id="${id}"]`);
			const taskTitle = hrefAction.querySelector('.task-title');
			const taskDescription = hrefAction.querySelector('.task-description');
			const taskProjectName = hrefAction.querySelector('.task-project-name');

			taskTitle.textContent = title;
			taskDescription.innerHTML = description;
			taskProjectName.textContent = project_name;
		}
	}

	// function showUpdatedExcelTask(data)
	// {
	// 	$('#waitModal').modal('hide');
	// 	if(!!data.success) {
	// 		let {id, date_due, description, title, fields} = data.success.answer;	
	// 		const tr_excel = tableExcel.querySelector(`tr[data-task_id="${id}"]`);
	// 		if (tr_excel) {
	// 			tr_excel.dataset['date_due'] = date_due;
	// 			tr_excel.classList.add(hideTask(date_due));
	// 			const taskTitle = tr_excel.querySelector('.ticket-name'),
	// 				taskDate = tr_excel.querySelector('.ticket-date'),
	// 				taskDescription = tr_excel.querySelector('.ticket-descr'),
	// 				taskTicket = tr_excel.querySelector('.ticket-ticket'),
	// 				taskCapop = tr_excel.querySelector('.ticket-capop'),
	// 				taskOracle = tr_excel.querySelector('.ticket-oracle');

	// 			const descr_spaces = cr2spaces(description);
				
	// 			// taskTitle.textContent = title;
	// 			taskDescription.innerHTML = descr_spaces;
	// 			taskDate.innerHTML = timestampToDate(date_due, false);
	// 			taskTicket.textContent = fields['ticket'];
	// 			taskCapop.textContent = fields['capop'];
	// 			taskOracle.textContent = fields['oracle'];

	// 			// taskTitle.dataset['item_value'] = title;
	// 			taskDescription.dataset['item_value'] = descr_spaces;
	// 			taskDate.dataset['item_value'] = timestampToDate(date_due, false);
	// 			taskTicket.dataset['item_value'] = fields['ticket'];
	// 			taskCapop.dataset['item_value'] = fields['capop'];
	// 			taskOracle.dataset['item_value'] = fields['oracle'];
	// 		}
	// 	}
	// }

	function showAllTasks(data)
	{
		if(!!data.success) {
			getDataFromKanboard('getTagsByProject', apiCallbackProps, ticketProjectName);
			ticketsContainer.textContent = '';
			data.success.answer.sort(byField('date_creation'));
			data.success.answer.forEach(function ({
				id, creator_id, date_completed, date_creation, description, title, project_name, files
			}) 
			{
				ticketsContainer.insertAdjacentHTML('beforeend', `
					<div class="task-ticket" data-task_id="${id}">
						<a href="#" class="task-action" data-task_id="${id}">#${id}</a>
						<h6 class="task-title">${title}</h6>
						<p class="task-description">${description}</p>
						<p class="task-project">Project: <span class="task-project-name">${project_name}</span></p>
						<div class="task-footer">
							<span id="task_id_${id}" class="file-attach">${filesAttached(files)}</span>
							<span>${timestampToDate(date_creation)}</span>
						</div>
					</div>
				`);
			});
		}
		else if (!!data.error) {
			containerError.innerText = data.error.error;
			containerError.classList.remove('d-none');
		}
		$('#waitModal').modal('hide');
	}

	const showBoardTable = (data) => {
		if(!!data.success) {
			tableExcel.textContent = '';
			data.success.answer.forEach(function ({
				id, date_due, date_started, title, reference, description, fields, assignee_name, editable
			}) 
			{
				const descr_spaces = cr2spaces(description);
				const time_started = timestampToTime(date_started);
				let disable_edit = (editable === 0) ? "invisible" : "";

				
				tableExcel.insertAdjacentHTML('beforeend', `
					<tr class="task-ticket-excel ${hideTask(date_started)}" data-task_id="${id}" data-date_started="${date_started}">
						<td class="ticket-date" data-item_value="${timestampToDate(date_started, false)}" data-item_id="inputDate">${timestampToDate(date_started, false)} ${time_started}</td>
						<td class="ticket-name" data-item_value="${assignee_name ?? '&nbsp;'}" data-item_id="inputName">${assignee_name ?? '&nbsp;'}</td>
						<!--<td class="ticket-descr" data-item_value="${descr_spaces}" data-item_id="inputDescr">${descr_spaces}</td>-->
						<td class="ticket-title-table" data-item_value="${title}" data-item_id="inputTitle">${title}</td>
						<td class="ticket-ticket" data-item_value="${reference}" data-item_id="inputTicket">${reference}</td>
						<td class="ticket-capop" data-item_value="${fields['capop']}" data-item_id="inputCapOp">${fields['capop']}</td>
						<td class="ticket-oracle" data-item_value="${fields['oracle']}" data-item_id="inputOracle">${fields['oracle']}</td>
						<td class="text-center" data-item_value="${time_started}" data-item_id="inputTime">
							<a href="#" class="${disable_edit}"><img class="icon-edit" src="img/edit.svg"></a>
						</td>
						<td class="text-center">
							<a href="#" class="${disable_edit}"><img class="icon-delete" src="img/delete.svg"></a>
						</td>
					</tr>
				`);
			});
		}
		$('#waitModal').modal('hide');
	};

	const showStatisticsTable = (data) => {
		if (!!dataTableObj) {
			dataTableObj.clear().destroy();
		}
		if (!!data.success) {
			tableStatistics.textContent = '';
			data.success.answer.forEach(function ({project_name, title, date_creation, fields}) {
				tableStatistics.insertAdjacentHTML('beforeend', `
					<td>${project_name}</td>
					<td>${timestampToDate(date_creation, false)}</td>
					<td>${fields.otl}</td>
					<td>${title}</td>
					<td>${fields.creator}</td>
				`);
			});
		}
		dataTableObj = $('#table_statistics').DataTable({
		"columnDefs": [
			{ "orderable": false, "targets": [2, 3] },
			{ "width": "10%", "targets": [0, 1, 2, 4] },
		],
		"order": [
			[0, 'asc'],
			[1, 'asc']
		],
		// "autoWidth": true,
		"paging": false,
		"searching": true,
		});
		$('#waitModal').modal('hide');
	};

	const showStatusTable = (data) => {
		if (!!data.success) {
			tableStatus.textContent = '';
			data.success.answer.sort(byField('date_creation'));
			data.success.answer.forEach(function ({id, title, assignee_name, status, date_creation, reference, description, project_name, fields}) {
				const submitted_name = getField(fields, 'creator', '');
				const originTaskID = getField(fields, 'origintask', id);
				tableStatus.insertAdjacentHTML('beforeend', `
				 	<tr class="task-ticket-status" data-task_id="${id}" data-origin_id="${originTaskID}">
						<td>${id}</td>
						<td data-item_value="${title}" data-item_id="titleStatus">${title}</td>
						<td data-item_value="${submitted_name}" data-item_id="creatorStatus">${submitted_name}</td>
						<td data-item_value="${getOTL(fields)}" data-item_id="OTLStatus">${assignee_name}</td>
						<td>${timestampToDate(date_creation, false)}</td>
						<td data-item_value="${description}" data-item_id="ticketDescriptionStatus">${reference}</td>
						<td data-item_value="${project_name}" data-item_id="inputProjectStatus">${status}</td>
						<td class="text-center">
							<a href="#"><img class="icon-edit" src="img/edit.svg"></a>
						</td>
					</tr>
				`);
			});
			
		}
		$('#waitModal').modal('hide');
	};

	const getOTL = (fieldsKanboard) => {
		let OTLStatus = '';
		if (!!fieldsKanboard['otl'] && fieldsKanboard['otl'] !== '') {
			OTLStatus = fieldsKanboard['otl'];
		}
		return OTLStatus;
	};

	function showUsers(data) {
		if(!!data.success) {
			clearInputsForms();
			usersList.textContent = '';
			data.success.answer.forEach((item) => {
				usersList.insertAdjacentHTML('beforeend', `
						${fillUserRights(item)}
				`);
			}) ;
		}
	}

	function fillUserRights(userRights) {
		let userName = '';
		let oneUserRights = defaultUserRights.slice();
		// 
		for (const [key, value] of Object.entries(userRights)) {
			if (key === 'user') {
				let idx = sections.indexOf(key);
				if (idx >= 0) {
					userName = value;
					oneUserRights[idx] = `<td data-${key}>${userName}</td>`;
				}
			} else if (key === 'rights') {
				value.forEach((right) => {
					let idx = sections.indexOf(right.sectionName);
					if (idx >= 0) {
						oneUserRights[idx] = `<td data-mode="${right.sectionName}" data-select="${right.accessType}">${right.accessType}</td>`;
					}
				});
				let idx = sections.indexOf('action');
				if (idx >= 0) {
					oneUserRights[idx] = `<td>
						<a href="#" data-userName="${userName}" data-action="delUser" title="Delete user"><img class="icon-delete" src="img/delete.svg"></a>
						<a href="#" data-userName="${userName}" data-action="setRights"><img class="icon-edit" src="img/edit.svg"></a>
					</td>`;
				}
			}
		}
		return `
			<tr data-userName="${userName}">
				${oneUserRights.reduce((res, current) => res + current, '')}
			</tr>`;
	}

	//init main
	btnClearSettings.addEventListener('click', clearEditableFields);
	btnUpdateTask.addEventListener('click', updateTask);
	ticketsContainer.addEventListener('click', actionTask);
	formNewTask.addEventListener('submit', (e) => {
		e.preventDefault();
	});
	formNewTask.addEventListener('reset', (e) => {
		e.preventDefault();
	});
	btnAddTask.addEventListener('click', (e) => {
		createTask(showAddedTask);
	});

	btnCreateTaskFile.addEventListener('change', (e) => {
		createTaskFileNew(e, attachmentsContainer);
	});

	[attachmentsContainer, attachmentsContainerStatus].forEach(item => {
			item.addEventListener('click', attachmentsAction);
		});
		
	tableUsers.addEventListener('click', actionForUsers);
	rightsForm.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		let newRights = [];
		let userName = '';
		for (let [key, value] of formData.entries()) {
			if (key === 'userName') {
				userName = value;
				continue;
			}
			newRights.push({
				pageName: capitalize(key),
				sectionName: key,
				sectionAttr: key,
				accessType: value,
			});
		}
		if (userName != '') {
			modRights(userName, newRights);
		}
	});

	newUserForm.addEventListener('reset', (e) => {
		btnAddUser.textContent = 'Add';
		newUserForm.dataset['method'] = 'addUser';
		newUsernameInput.readOnly = false;
		rightsForm.reset();
	});

	rightsForm.addEventListener('reset', (e) => {
		setRightsContainer.style.display = 'none';
	});

	formsAuth.forEach(form => {
		form.addEventListener('submit', (e) => {
			e.preventDefault();
			const dataMethod = form.dataset['method'];
			if (dataMethod) {
				const formData = new FormData(form);
				const authData = {};
				for (let [key, value] of formData.entries()) {
					authData[key] = value.trim();
				}
				authData['method'] = dataMethod;
				signIn(authData);
			}
		});
	});

	// init excel
	tableExcel.addEventListener('click', editExcelTask);
	periodSelect.addEventListener('click', periodChange);
	btnRemove.addEventListener('click', removeTask);
	ticketEditForm.addEventListener('submit', (e) => {
		e.preventDefault();
		updateTaskFull();
	});
	ticketEditForm.addEventListener('reset', (e) => {
		btnUpdateTicket.dataset['task_id'] = 0;
		btnUpdateTicket.disabled = true;
	});

	exportExcel.addEventListener('click', (e) => {
		e.preventDefault();
		document.location.href=`./${requestURL}?method=doDataExport&status=all&section=excel&days=${periodDays}`;
	});

	//init status
	tableStatus.addEventListener('click', editStatusTask);
	btnCreateTaskFileStatus.addEventListener('change', (e) => {
		createTaskFileNew(e, attachmentsContainerStatus);
	});
	formNewTaskStatus.addEventListener('reset', () => {
		attachmentsAreaStatus.classList.add('invisible');
		attachmentsContainerStatus.textContent = '';
		formNewTaskStatus.querySelectorAll('[data-disable_on_update="1"]').forEach(item => {
			item.readOnly = false;
			item.classList.remove('text-muted');
		});
		btnCreateTaskFileStatus.removeAttribute('task_id');
		taskStatus_id.value = 0;
		ticketDescriptionStatus.textContent = '';
		btnUpdateTaskStatus.classList.add('d-none');
		btnAddTaskStatus.classList.remove('d-none');
		previousElem = null;
	});

	formNewTaskStatus.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const arrData = {};
		let method = 'createTask';
		for (let [key, value] of formData.entries()) {
			if (typeof value == 'object') continue;
			arrData[key] = value.trim();
		}
		arrData['description'] = ticketDescriptionStatus.innerText;
		if (taskStatus_id.value != 0) {
			arrData['version'] = 1;
		}
		const body = {
			method: method,
			params: arrData,
		};
			sendRequest('POST', requestURL, body).then(getTaskStatus);
	});

	// init statistics
	exportStatistics.addEventListener('click', (e) => {
		e.preventDefault();
		document.location.href=`./${requestURL}?method=doDataExport&status=all&section=statistics`;
	});


	clearInputsForms();
	iniInterface();
});