'use strict';

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

const sections2 = {
	'user': {show : 1},
	'main': {show : 1, rights: ['admin']},
	'status': {show: 0, rights: ['user', 'admin']},
	'excel': {show: 0, rights: ['user', 'admin']},
	'statistics': {show: 0, rights: ['user', 'admin']},
	'action': {show: 0, rights: ['user', 'admin']},
};

const defaultUserRights = [];
const defaultUserRights2 = [];

sections.forEach((item, idx) => {
	defaultUserRights[idx] = `<td data-${item}>&nbsp</td>`;
});

// Object.keys(sections2).forEach(item => {
// 	console.log(item);
// });

const textCreatorHeader = 'Submitted by:';
const textOTLHeader = 'OTL:';

let fileAttach;
let pageStatus = 0, fileDeleted = 0, totalWaits = 0;

const errorMsg = document.createElement('div');
errorMsg.textContent = 'Username or password is incorrect';

// common functions
// for sort in ORDER DESC
const byField = (field) => {
	return (a, b) => a[field] > b[field] ? -1 : 1;
};

const timestampToDate = (timestampValue, timeOut = true) => {
	if(!timestampValue) {
		return '&nbsp;';
	}
  const a = new Date(timestampValue * 1000);
  const months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
  let dateOut = `${a.getFullYear()}-${months[a.getMonth()]}-${addZero(a.getDate())}`;
  if(timeOut) {
	dateOut = `${dateOut} ${addZero(a.getHours())}:${addZero(a.getMinutes())}:${addZero(a.getSeconds())}`;
  }
  return dateOut;
}

const dateToTimestamp = date_str => {
	const date = new Date(date_str);
	const ts = date.valueOf() / 1000;
	if (!isNaN(ts)) {
		return ts;
	}
	return 0;
};

const tsPeriod = () => {
	const today = new Date();
	const nextDay = new Date();
	nextDay.setDate(nextDay.getDate() + periodDays - 1);
	const dayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
	const dayEnd = new Date(nextDay.getFullYear(), nextDay.getMonth(), nextDay.getDate(), 23, 59, 59);
	return {
		dayStart: dayStart.valueOf() / 1000,
		dayEnd: dayEnd.valueOf() / 1000,
	}
};

const hideTask = (date_due) => {
	let {dayStart, dayEnd} = tsPeriod();
	let hideTaskClass = 'd-none';
	
	if(!date_due || (date_due > dayStart && date_due < dayEnd)) {
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

const escapeHTML = (string) => {
	return String(string).replace(/[&<>"'`=\/]/g, (s) => entityMap[s]);
};

const capitalize = (string) => string.charAt(0).toUpperCase() + string.slice(1);

window.addEventListener('DOMContentLoaded', () => {
	const requestURL = 'backend.php';
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
		containerError = document.querySelector('.container-error'),
		usersList = document.querySelector('.users-list'),
		tableUsers = document.querySelector('.table-users'),
		setRightsContainer = document.querySelector('.set-rights');
	
	async function sendRequest(method, url, body, showWait = false) {
		const headers = {
			'Content-Type': 'application/json'
		};
		if(showWait) {
			// totalWaits++;
			// setTimeout(() => {
				$('#waitModal').modal('show');
			// }, 500);
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
			// 	$('#waitModal').modal('hide');
			// }
			return data;
		} catch (e) {
			console.error(e);
			if(showWait) {
				$('#waitModal').modal('hide');
			}
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

	// $('#login-dialog')

	const iniInterface = (useSignIn = false) => {
		clearEditableFields();
		let body = {
			method: 'getRights',
		};
		if (useSignIn) {
			body = {
				method: 'signin',
				// params: {
				// 	user: 'all',
				// },
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
					section[idx].dataset['showned'] = '1';
					getAllTask();
					// getDataFromKanboard('getTagsByProject', apiCallbackProps, ticketProjectName);
				}
				break;
			case 'settings':
				if (!section[idx].dataset['showned']) {
					section[idx].dataset['showned'] = '1';
					getKanboardUsers();
				}
				break;
			default:
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
				// $('#login-dialog').modal('show');
				// iniInterface(true);
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
			}
			data.success.answer.rights.forEach(({pageName, sectionAttr, sectionName, accessType}) => {
				if (accessType != '') {			
					menu.insertAdjacentHTML('beforeend', `
					<li data-section="${sectionAttr}">${pageName}</li>
					`);
				}
			});
		}
		menu.insertAdjacentHTML('beforeend', `
			<li data-section="${loginAction}">${capitalize(loginAction)}</li>
		`);
		$('#waitModal').modal('hide');
		menu.children[0].style.backgroundColor = 'rgba(0,0,0,0.1)';
		// toggleSection('main');
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

	const toggleToUpdateMode = () => {
		btnUpdateTask.classList.remove('d-none');
		btnAddTask.classList.add('d-none');
		attachmentsArea.classList.remove('invisible');
	};

	const apiCallbackProps = {
		'getTagsByProject': function (data, container) {
			fillProjectsList(data, container);
		},
	};

	const fillFileInfo = (fileInfo) => {
		let { file_id, file_name, file_size } = JSON.parse(fileInfo);
		attachmentsContainer.insertAdjacentHTML('beforeend', `
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
			},
		};
		sendRequest('POST', requestURL, body, true).then(showUpdatedTask);
	};

	const getAllTask = () => {
		const body = {
			method: 'getAllTasks',
		}
		sendRequest('POST', requestURL, body, true).then(showAllTasks);
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

	const showAddedFile = (resultFileList) => 
	{
		if (!!resultFileList.success.answer) {
			const taskID = resultFileList.success.answer.id;
			fillFileTaskInfo(taskID);
			attachmentsContainer.textContent = '';
			(resultFileList.success.answer.files).forEach(fileItem => fillFileInfo(JSON.stringify(fileItem)));
		}
	};

	const showAddedUser = (data) => {
		console.log(Object.keys(data.success.answer).length);
		if (data.success.answer && (data.success.answer.length > 0 || Object.keys(data.success.answer).length > 0)) {
			getKanboardUsers();
		}
	};

	const removeFileFromList = (resultFileList) => {
		if (!!resultFileList.success.answer) {
			if (fileDeleted != 0) {
				fileDeleted.remove();
				// removeElement(fileDeleted);
				fileDeleted = 0;
				const taskID = resultFileList.success.answer.id;
				fillFileTaskInfo(taskID);
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

	const fillFileTaskInfo = taskID => {
		try {
			const fileTaskInfo = document.querySelector('#task_id_' + taskID);
			fileTaskInfo.innerHTML = filesAttached(resultFileList.success.answer.files);
		}
		catch (e) {}
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
				JSON.parse(filesList.dataset['files_list']).forEach(fillFileInfo);
			}
			ticketTitle.value = taskTitle.textContent;
			ticketProjectName.value = taskProjectName.textContent;
			btnUpdateTask.dataset['task_id'] = taskID;
			toggleToUpdateMode();
			// viewEditablePanel();
		}
	};

	const clearEditableFields = () => {
		ticketTitle.value = '';
		ticketCreator.value = '';
		ticketOTL.value = '';
		ticketProjectName.value = '';
		ticketDescription.textContent = '';
		btnUpdateTask.classList.add('d-none');
		btnAddTask.classList.remove('d-none');
		attachmentsArea.classList.add('invisible');
		btnUpdateTask.dataset['task_id'] = 0;
	};

	const createTaskFile = () => {
		const formData = new FormData();
		formData.append('file', btnCreateTaskFile.files[0]);
		formData.append('method', 'createTaskFile');
		formData.append('id', btnUpdateTask.dataset['task_id']);
		sendFile('POST', requestURL, formData).then(showAddedFile);
		btnCreateTaskFile.value = '';
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
			sendRequest('POST', requestURL, body).then(removeFileFromList);
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

	function showAddedTask(data)
	{
		btnUpdateTask.dataset['task_id'] = data.success.answer.id;
		attachmentsContainer.textContent = '';
		toggleToUpdateMode();
		// getAllTask();
		if(!!data.success) {
			let {id, creator_id, date_completed, date_creation, description, title, project_name, files = []} = data.success.answer;

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

	function showUpdatedTask(data)
	{
		$('#waitModal').modal('hide');
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
			ticketsContainer.addEventListener('click', actionTask);
		}
		else if (!!data.error) {
			containerError.innerText = data.error.error;
			containerError.classList.remove('d-none');
		}
		$('#waitModal').modal('hide');
	}

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
	formNewTask.addEventListener('submit', (e) => {
		e.preventDefault();
	});
	formNewTask.addEventListener('reset', (e) => {
		e.preventDefault();
	});
	btnAddTask.addEventListener('click', (e) => {
		createTask(showAddedTask);
	});
	btnCreateTaskFile.value = '';
	btnCreateTaskFile.addEventListener('change', createTaskFile);
	attachmentsContainer.addEventListener('click', attachmentsAction);
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

	clearInputsForms();
	iniInterface();


	// viewEditablePanel();
});