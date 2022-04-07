'use strict';

const requestURL = 'backend.php';
const automatorURL = 'utils.php';

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
	'automator',
	'action',
];

const defaultUserRights = [];

sections.forEach((item, idx) => {
	defaultUserRights[idx] = `<td data-${item}>&nbsp</td>`;
});

const textCreatorHeader = 'Submitted by:';
const textOTLHeader = 'OTL:';

const startDocumentTitle = document.title;
let currentHash = location.hash.substring(1);

let fileAttach;
let pageStatus = 0, fileDeleted = 0, totalWaits = 0, periodDays = 0;
let currentUser = '';
let previousElem = null;

let dayStartExcel, dayEndExcel = 0;

const table_statistics_selector = 'table_statistics';
const exportStatistics_selector = 'exportStatistics';
const table_excel_selector = 'table_excel';
const exportExcel_selector = 'exportExcel';

const excelDataArray = [];

const selectCnange = new Event('change');

const errorMsg = document.createElement('div');
errorMsg.textContent = 'Username or password is incorrect';

let filesTemplate, xls_files;

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

function tsPeriodDays (periodDays) {
	const today = new Date(), prevDay = new Date();
	let dayStart = 0, dayEnd = 0;
	const currTime = today.getTime();
	if (periodDays == 7 || periodDays == 14) {
		const dayOfWeek = today.getDay();
		prevDay.setTime(currTime - dayOfWeek * 24 * 3600 * 1000);
		today.setTime(currTime + (periodDays - dayOfWeek - 1) * 24 * 3600 * 1000);
	} else if (periodDays == 31 || periodDays == 62) {
		prevDay.setDate(1);
		today.setMonth(today.getMonth() + 1 * (periodDays / 31), 0);
	} else if (periodDays == 365) {
		prevDay.setMonth(0, 1);
		today.setMonth(12, 31);
	} else {
		prevDay.setDate(prevDay.getDate() - periodDays);
		if (periodDays < 0)
		{
			today.setDate(today.getDate() - periodDays);
		}
	}
	dayStart = new Date(prevDay.getFullYear(), prevDay.getMonth(), prevDay.getDate(), 0, 0, 0);
	dayEnd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59);
	return {
		dayStart: dayStart.getTime() / 1000,
		dayEnd: dayEnd.getTime() / 1000,
	}
}

const hideTask = ({date_started, dayStart, dayEnd, hideTaskNoDate}) => {
	let hideTaskClass = 'dt-row-none';
	if(!(date_started || hideTaskNoDate) || (date_started > dayStart && date_started < dayEnd)) {
		hideTaskClass = 'not-used';
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

function downloadFile(dataurl) {
  const link = document.createElement("a");
  link.href = `data:text/plain;charset=utf-8, ${encodeURIComponent(dataurl)}`;
  link.click();
  link.remove();
}

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
	const ticketExcelForm = document.querySelector('#ticketExcelForm'),
		holdStatus = document.querySelector('#holdStatus'),
		ticketDescriptionExcel = document.querySelector('#ticketDescriptionExcel'),
		btnUpdateTaskExcel = document.querySelector('.btn-update-task-excel'),
		btnAddTaskExcel = document.querySelector('.btn-add-task-excel'),
		tableExcel = document.querySelector('.table-excel'),
		btnRemove = document.querySelector('.btn-remove'),
		inputName = document.querySelector('#inputName'),
		inputDate = document.querySelector('#inputDate'),
		inputTime = document.querySelector('#inputTime'),
		inputTitle = document.querySelector('#inputTitle'),
		ticketCreatorExcel = document.querySelector('#creatorExcel'),
		// ticketOTLExcel = document.querySelector('#OTLExcel'),
		inputStatus =  document.querySelector('#inputStatus'),
		ticketTitleExcel = document.querySelector('.ticket-title-excel'),
		ticketProjectNameExcel = document.querySelector('#inputProjectExcel'),
		taskExcel_id = document.querySelector('#taskExcel_id'),
		btnCreateTaskFileExcel= document.querySelector('#attachFileExcel'),
		attachmentsContainerExcel = document.querySelector('.attachments-container-excel');
	// status elements
		const formNewTaskStatus = document.querySelector('#formNewTaskStatus'),
			btnUpdateTaskStatus = document.querySelector('.btn-update-task-status'),
			btnAddTaskStatus = document.querySelector('.btn-add-task-status'),
			ticketDescriptionStatus = document.querySelector('#ticketDescriptionStatus'),
			btnCreateTaskFileStatus = document.querySelector('#attachFileStatus'),
			attachmentsContainerStatus = document.querySelector('.attachments-container-status'),
			ticketProjectNameStatus = document.querySelector('#inputProjectStatus'),
			taskStatus_id = document.querySelector('#taskStatus_id'),
			attachmentsAreaStatus = document.querySelector('.attachments-area-status'),
			tableStatus = document.querySelector('.table-request'),
			ticketCreatorStatus = document.querySelector('#creatorStatus'),
			ticketOTLStatus = document.querySelector('#OTLStatus');
		// statistics elements
		// const tableStatistics = document.querySelector('.table-statistics');
			// exportStatistics = document.querySelector('#exportStatistics');
		// automator elements
		const listDevices = document.querySelector('#listDevices'),
			listTemplates = document.querySelector('#uploadedTemplates'),
			formTeplateUpload = document.querySelector('#formTeplateUpload'),
			formDevicesUpload=document.querySelector('#formDevicesUpload'),
			errorMsgAutomator = document.querySelector('#upload_error'),
			errorMsgDevices = document.querySelector('#upload_devices_error'),
			modalCommand = document.querySelector('#btnDialogModal'),
			// btnTemplateSelect = document.querySelector('#buttonTemplateSelect'),
			btnDevicesSelect = document.querySelector('#buttonDevicesSelect');

	const periodChange = (e, buttonApi, dataTable, node, config) => {
		const target = buttonApi.nodes()[0];
		const periodSelect = buttonApi.container()[0];

		if (target.classList.contains('btn') && !target.classList.contains('hideNoDate')) {
		
			let hideNoData = false;
			for (const item of periodSelect.children)
			{
				if (item.tagName === 'BUTTON') {
					if (item.dataset['hideNoDate'] == '1') {
						hideNoData = true;
					} else {
						item.classList.remove('btn-primary');
						item.classList.add('btn-secondary');
					}
				}
			}
			target.classList.remove('btn-secondary');
			target.classList.add('btn-primary');
			periodDays = parseInt(target.dataset['days'], 10);
			if (isNaN(periodDays) || periodDays > 365 || periodDays < -1) {
				periodDays = 0;
			}

			refreshBoardTable(hideNoData);
			
		}
	};

	const dataTableStatistics = $(`#${table_statistics_selector}`)
		.on('init.dt', function () {
				// const btnExcel = document.querySelector(`#${exportStatistics_selector}`);
				// btnExcel.className = "";
				// const img = document.createElement('img');
				// img.src = 'img/file-excel.svg';
				// img.classList.add("icon-excel");
				// btnExcel.append(img);
				const btnExcel = document.querySelector(`.native-excel`);
				// btnExcel.className = "";
				const img = document.createElement('img');
				img.src = 'img/file-excel.svg';
				img.classList.add("icon-excel");
				btnExcel.append(img);
		})
		.DataTable({
			dom: '<"statistics-menu"Bft>',
			buttons: 
			{
				dom: {
					container: {
						tag: 'div',
						className: 'statistics-buttons'
					},
					button: {
						tag: 'button',
						className: [
							'btn', 'btn-sm'
						]
					},
					buttonLiner: {
						tag: null
					}

				},
				buttons: [
					// {
					// 	text: null,
					// 	action: function (e, dt, node, config) {
					// 		e.preventDefault();
					// 		document.location.href = `./${requestURL}?method=doDataExport&status=all&section=statistics`;
					// 	},
					// 	attr: {
					// 		title: 'Export to Excel',
					// 		id: exportStatistics_selector
					// 	},
					// 	tag: 'a',
					// 	className: null,
					// },
					{
						extend: "excel",
						text: '',
						title: null,
						className: 'native-excel',
						filename: '* Statistics Export',
						tag: 'a',
						exportOptions: {
							columns: '.exportable'
						}
					}
				]
			},
			"autoWidth": false,
			columns:
			[
				{ "width": "10%" },
				{ "width": "10%" },
				{ "width": "10%" },
				{ "width": "55%" },
				{ "width": "10%" },
				{ "width": "5%" },
			],
			columnDefs: [
				{ orderable: false, "targets": [2, 3, 5] },
			],
			order: [
				[1, 'desc'],
				[0, 'asc'],
			],
			paging: false,
			searching: true,
			stripeClasses :[],
		});

	const dataTableExcel = $(`#${table_excel_selector}`)
		.on('init.dt', function () {
			const btnExcel = document.querySelector(`#${exportExcel_selector}`);
			btnExcel.className = "";
			const img = document.createElement('img');
			img.src = 'img/file-excel.svg';
			img.classList.add("icon-excel");
			btnExcel.append(img);
		})
		.DataTable({
		dom: '<"excel-menu"Bft>',
		buttons: 
		{
			dom: {
				container: {
					tag: 'div',
					className: 'period-select'
				},
				button: {
					tag: 'button',
					className: [
						'btn', 'btn-sm'
					]
				},
				buttonLiner: {
					tag: null
				}

			},
			buttons: [
				{
					text: '-1D',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '1'
					},
					className: 'btn-secondary',
				},
				{
					text: 'Today',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '0'
					},
					className: 'btn-primary',
				},
				{
					text: '+1D',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '-1'
					},
					className: 'btn-secondary',
				},
				{
					text: 'Week',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '7'
					},
					className: 'btn-secondary',
				},
				{
					text: '2 Weeks',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '14'
					},
					className: 'user-none btn-secondary',
				},
				{
					text: 'Month',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '31'
					},
					className: 'user-none btn-secondary',
				},
				{
					text: '2 Months',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '62'
					},
					className: 'user-none btn-secondary',
				},
				{
					text: 'YTD',
					action: function (e, dt, node, config) {
					},
					attr: {
						'data-days': '365'
					},
					className: 'user-none btn-secondary',
				},
				{
					text: 'Date',
					action: function (e, dt, node, config) {
						const target = node[0];
						let hideNoDate = false;
						if (target.classList.contains('btn-secondary')) {
							target.classList.remove('btn-secondary');
							target.classList.add('btn-warning');
							target.dataset['hideNoDate'] = '1';
							hideNoDate = true;
						} else {
							target.classList.add('btn-secondary');
							target.classList.remove('btn-warning');
							target.dataset['hideNoDate'] = '0';
						}
						refreshBoardTable(hideNoDate);
					},
					attr: {
						id: 'hideNoDate'
					},
					className: 'hideNoDate btn-secondary',
				},
				// {
				// 	text: null,
				// 	action: function (e, dt, node, config) {
				// 		e.preventDefault();
				// 		document.location.href = `./${requestURL}?method=doDataExport&status=all&section=excel&days=${periodDays}`;
				// 	},
				// 	attr: {
				// 		title: 'Export to Excel',
				// 		id: exportExcel_selector
				// 	},
				// 	tag: 'a',
				// 	className: null,
				// },
				{
					extend: "excel",
					text: '',
					title: null,
					// className: 'native-excel',
					filename: '* Excel Export',
					attr: {
						title: 'Export to Excel',
						id: exportExcel_selector
					},
					tag: 'a',
					exportOptions: {
						columns: '.exportable'
					}
				}
			]
		},
		"autoWidth": false,
		columns:
		[
			{ "width": "5%" },
			{ "width": "10%" },
			null, 
			{ "width": "45%" }, 
			{ "width": "10%" }, 
			{ "width": "5%" }, 
			{ "width": "5%" }, 
			{ "width": "5%" }, 
			{ "width": "3%" }, { "width": "3%" },
		],
		"columnDefs": [
			{ "orderable": false, "targets": [3, 4, 5, 6, 7, 8, 9] },
			{
				targets: -1,
				className: 'dt-head-left'
			},
		],
		"order": [
			[0, 'asc'],
			[1, 'asc'],
			[2, 'asc']
		],
		paging: false,
		searching: true,
		info: false,
		stripeClasses :[],
	})
	.on('buttons-action', periodChange);
	
	btnUpdateTaskExcel.disabled = true;
	btnUpdateTaskExcel.dataset['task_id'] = 0;
	
	async function sendRequest(method, url, body, showWait = false) {
		containerError.classList.add('d-none');
		const headers = {
			'Content-Type': 'application/json'
		};
		if(showWait) {
		// 	// setTimeout(() => {
				// $('#waitModal').modal('show');
		// 	// }, 500);
		}
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
			$('#waitModal').modal('hide');
		}
	}

	async function sendRequestAutomator(method, url, body) {
		try {
			const response = await fetch(url, {
				method: method,
				body: body,
			});
			const data = await response.json();
			return data;
		} catch (e) {
			console.error(e);
			$('#waitModal').modal('hide');
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
			location.reload();
		});
	};

	const toggleSection = (showSection) => {
		document.title = startDocumentTitle;
		location.hash = showSection;
		let idx = 0;
		section.forEach((item, i) => {
			item.style.display = 'none';
			if (showSection && item.classList.contains(showSection)) {
				idx = i;
			}
		});
		section[idx].style.display = 'block';

		clearOldData(showSection);
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
			case 'settings':
				if (!section[idx].dataset['showned']) {
					// section[idx].dataset['showned'] = '1';
					getKanboardUsers();
				}
				break;
			case 'automator':
				document.title = 'Automator';
				getAutomator();
				break;
			default:
				break;
		}
	};

	const clearOldData = (...sections) => {
		for (let section of sections) {
			let dataContainer = document.querySelector(`section.${section} .dynamic-data`);
			if (dataContainer) {
				dataContainer.textContent = '';
			}
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
				selectMenuItem(target.parentNode, target.dataset['section']);
				toggleSection(target.dataset['section']);
			}
		}
	});

	const selectMenuItem = (menu, section) => {
		Array.from(menu.children).forEach(item => {
			item.style.backgroundColor = '';
		});
		try {
			menu.querySelector(`[data-section="${section}"]`).style.backgroundColor = 'rgba(0,0,0,0.1)';
		} catch (e) {};
	};

	const showInterface = (data) => {
		let loginAction = 'logout';
		if(data) {
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
			let section = 'main';
			if (currentHash === 'automator') {
				section = 'automator';
			}
			selectMenuItem(menu, section);
			toggleSection(section);
		}
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

	const toggleToUpdateMode = (btnUpdate, btnAdd, attachments = null) => {
		btnUpdate.classList.remove('d-none');
		btnAdd.classList.add('d-none');
		if (attachments) {
			attachments.classList.remove('invisible');
		}
	};

	const apiCallbackProps = {
		'getTagsByProject': function (data, container) {
			fillSelect(data, container);
		},
		'getColumns': function (data, container) {
			fillSelect(data, container);
		},
		'getAssignableUsers': function (data, container) {
			fillSelect(data, container);
		},
	};

	const fillFileInfo = (fileInfo, attachmentsList) => {
		let { file_id, file_name, file_size } = JSON.parse(fileInfo);
		attachmentsList.insertAdjacentHTML('beforeend', `
			<p class="file-delete-container">File name: <a href="${requestURL}?method=downloadTaskFile&id=${file_id}" class="file-download">${file_name}</a>, Size: ${file_size}
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
		if (ticketTitle.value.trim().length == 0 || ticketCreator.value.trim().length == 0 || ticketDescription.innerText.trim().length == 0)
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
		sendRequest('POST', requestURL, body).then(getAllTask);
	};

	const getAllTask = () => {
		formNewTask.reset();
		const body = {
			method: 'getAllTasks',
		}
		sendRequest('POST', requestURL, body, true).then(showAllTasks);
		ticketCreator.defaultValue = currentUser;
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
		clearOldData(action);
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

	const fillSelect = (data, elemList) => {
		if(!!data.success) {
			elemList.innerHTML = '<option value="" selected disabled hidden>Choose...</option>';
			data.success.answer.forEach(function (item) {
				elemList.insertAdjacentHTML('beforeend', `
					<option value="${item}">${item}</option>
				`);
			});
		}
		elemList.value = '';
	};

	const editExcelTask = (e) => {
		e.preventDefault();
		const target = e.target;
		if (target.classList.contains('icon-edit')) {
			const taskTicket = target.closest('.task-ticket-excel');
			if(!!taskTicket) {
				clearExcelTicketFields();
				toggleToUpdateMode(btnUpdateTaskExcel, btnAddTaskExcel);
				selectTR('.task-ticket-excel', taskTicket);
				const taskID = taskTicket.dataset['task_id'];
				if (taskID !== 0) {
					btnCreateTaskFileExcel.setAttribute('task_id', taskID);
					getAllTaskFiles(taskID, attachmentsContainerExcel);
				}
				for(const item of taskTicket.children)
				{
					const itemValue = item.dataset['item_value'];
					const inputID = item.dataset['item_id'];
					if(!itemValue || !inputID) continue;
					let inputElem = document.querySelector(`#${inputID}`);
					try {
						if (inputElem.tagName === 'DIV') {
							inputElem.innerText = itemValue;
						} else {
							inputElem.value = itemValue;
						}
					} catch (e) {}
				}
				// 
				const taskDescription = ticketDescriptionExcel.innerText;
				const positionCreator = taskDescription.lastIndexOf(textCreatorHeader);
				const positionOTL = taskDescription.lastIndexOf(textOTLHeader);
				let extDescriptionPosition = 0;
				let endPositionOTL, endPositionCreator = 0;

				if (positionOTL !== -1) {
					extDescriptionPosition = positionOTL;
				} else {
					// ticketOTLExcel.value = '';
				}
				if (positionCreator !== -1) {
					if (!extDescriptionPosition || (extDescriptionPosition > positionCreator)) {
						extDescriptionPosition = positionCreator;
					}
				} else {
					ticketCreatorExcel.value = '';
				}
				if (extDescriptionPosition) {
					ticketDescriptionExcel.innerText = taskDescription.substring(0, extDescriptionPosition).trim();
					if (positionOTL < positionCreator) {
						endPositionOTL = positionCreator;
						endPositionCreator = taskDescription.length;
					} else {
						endPositionCreator = positionOTL;
						endPositionOTL = taskDescription.length;
					}
					if (positionOTL !== -1) {
						// ticketOTLExcel.value = taskDescription.substring(positionOTL + textOTLHeader.length, endPositionOTL).trim();
					}
					if (positionCreator !== -1) {
						ticketCreatorExcel.value = taskDescription.substring(positionCreator + textCreatorHeader.length, endPositionCreator).trim();	
					}
				} else {
					ticketDescriptionExcel.innerText = taskDescription.trim();
				}

				taskExcel_id.value = taskID;
				btnUpdateTaskExcel.dataset['task_id'] = taskID;
				btnUpdateTaskExcel.disabled = false;

				inputStatus.dispatchEvent(selectCnange);
			}
		} else if (target.classList.contains('icon-delete')) {
			const taskTicket = target.closest('.task-ticket-excel');
			if (!!taskTicket) {
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
				// ticketOTLStatus.value = '';
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
					// ticketOTLStatus.value = taskDescription.substring(positionOTL + textOTLHeader.length, endPositionOTL).trim();
				}
				if (positionCreator !== -1) {
					ticketCreatorStatus.value = taskDescription.substring(positionCreator + textCreatorHeader.length, endPositionCreator).trim();	
				}
			} else {
				ticketDescriptionStatus.innerText = taskDescription.trim();
			}
			formNewTaskStatus.addEventListener('input', toggleFormStatusToNew);
		}
	};

	const toggleFormStatusToNew = () => {
		let element = document.activeElement;
		if (element.tagName !== 'BODY' && element.type !== 'file') {
			btnUpdateTaskStatus.disabled = false;
			btnCreateTaskFileStatus.removeAttribute('task_id');
			attachmentsContainerStatus.textContent = '';
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
				JSON.parse(filesList.dataset['files_list']).forEach((item) => {
					fillFileInfo(item, attachmentsContainer);
				});
			}
			ticketTitle.value = taskTitle.textContent;
			ticketProjectName.value = taskProjectName.textContent;
			btnUpdateTask.dataset['task_id'] = taskID;
			btnCreateTaskFile.setAttribute('task_id', taskID);
			taskMain_id.value  = taskID;
			toggleToUpdateMode(btnUpdateTask, btnAddTask, attachmentsArea);
		}
	};

	const clearEditableFields = () => {
		formNewTask.reset();
	};

	const clearAllSection = (shownedSections) => {
		document.querySelectorAll(`[${shownedSections}]`).forEach(item => {
			item.removeAttribute(shownedSections);
		});
		clearOldData('main','status', 'statistics', 'excel');
	};

	const clearExcelTicketFields = () => {
		ticketExcelForm.reset();
	};

	const selectTR = (selector, taskTicket = null) => {
		const taskTickets = document.querySelectorAll(selector);
		taskTickets.forEach(item => {
			item.classList.remove('table-primary');
		});
		if (taskTicket) {
			taskTicket.classList.add('table-primary');
		}
	};

	const refreshBoardTable = (hideTaskNoDate = false) => {
		// const taskTickets = document.querySelectorAll('.task-ticket-excel');
		let {dayStart, dayEnd} = tsPeriodDays(periodDays);

		dataTableExcel.clear().rows.add(excelDataArray).draw();
		excelDataArray.forEach(item => {
			let date_started = parseInt(item.dataset['date_started'], 10);
			if (hideTask({date_started, dayStart, dayEnd, hideTaskNoDate}) === 'dt-row-none') {
				item.classList.add('dt-row-none');
			} else {
				item.classList.remove('dt-row-none');
			}
		});
		dataTableExcel.rows('.dt-row-none').remove().draw();
	};

	const createTaskFileNew = (e, btnFile, attachmentsList, callback = null) => {
		const target = e.target;
		const inputData = new FormData(target.form);
		let task_id = 0;
		const formData = new FormData();
		const fileStatusTaskID = btnFile.getAttribute('task_id');
		if (!!fileStatusTaskID) {
			task_id = fileStatusTaskID;
		}
		if (target.files[0]) {
			if (task_id != 0) {
				formData.append('file', target.files[0]);
				formData.append('method', 'createTaskFile');
				formData.append('id', task_id);
				sendFile('POST', requestURL, formData).then((data) => {
					showAddedFileNew(data, attachmentsList);
					if (callback) {
						callback();
					}
				});
			} else {
				attachmentsList.insertAdjacentHTML('afterbegin', `File name: ${target.files[0].name}, Size: ${target.files[0].size} (waiting for upload)<br>`);
			}
		}
	};

	const attachmentsAction = (e) => {
		const target = e.target;
		let fileAction = target.closest('.file-delete');
		if (!!fileAction) {
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
		rightsForm.reset();
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

	const removeTask = () => {
		$('#modalRemoveDialog').modal('hide');
		const body = {
			method: 'removeTask',
			params: {
				id: btnRemove.dataset['task_id'],
				section: ticketExcelForm.querySelector('#excelForm').value,
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
		getDataFromKanboard('getColumns', apiCallbackProps, inputStatus);
		getDataFromKanboard('getAssignableUsers', apiCallbackProps, inputName);
		getDataFromKanboard('getTagsByProject', apiCallbackProps, ticketProjectNameExcel);
		getBoard('excel');
		ticketCreatorExcel.defaultValue = currentUser;
	};

	const getTaskStatus = () => {
		formNewTaskStatus.reset();
		getDataFromKanboard('getTagsByProject', apiCallbackProps, ticketProjectNameStatus);
		getBoard('status');
		ticketCreatorStatus.defaultValue = currentUser;
	};

	const getTaskStatistics = () => {
		getBoard('statistics');
	};

	function attachFileStatus(data) {
		if (data.success && data.success.answer && data.success.answer.id && btnCreateTaskFileStatus.files[0]) {
			taskStatus_id.value = data.success.answer.id;
			btnCreateTaskFileStatus.setAttribute('task_id', data.success.answer.id);
			btnCreateTaskFileStatus.dispatchEvent(new Event('change'));
		} else {
			getTaskStatus();
		}
	}

	function attachFileExcel(data) {
		if (data.success && data.success.answer && data.success.answer.id && btnCreateTaskFileExcel.files[0]) {
			taskExcel_id.value = data.success.answer.id;
			btnCreateTaskFileExcel.setAttribute('task_id', data.success.answer.id);
			btnCreateTaskFileExcel.dispatchEvent(new Event('change'));
		} else {
			getTaskBoard();
		}
	}

	const getAutomator = () => {
		commonAutomatorRequest({
			formParams: {
				doShowAllDevives: 1,
			},
			URL: automatorURL,
			callback: showAutomator
		});
	};

	const doGetTemplates = () => {
		commonAutomatorRequest({
			formParams: {
				doGetTemplates: 1,
			},
			URL: automatorURL,
			callback: showTemplates
		});
	};

	function showAddedTask(data)
	{
		attachmentsContainer.textContent = '';
		toggleToUpdateMode(btnUpdateTask, btnAddTask, attachmentsArea);
		if(data.success && data.success.answer) {
			let {id, date_creation, description, title, project_name, files = []} = data.success.answer;
			btnUpdateTask.dataset['task_id'] = data.success.answer.id;
			btnCreateTaskFile.setAttribute('task_id', id);
			ticketsContainer.insertAdjacentHTML('afterbegin', `
					<div class="task-ticket" data-task_id="${id}">
						<a href="#" class="task-action" data-task_id="${id}">#${id}</a>
						<p class="task-title">${title}</p>
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

	function showAllTasks(data)
	{
		if(data && data.success) {
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
						<p class="task-title">${title}</p>
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
		clearExcelTicketFields();
		excelDataArray.length = 0;
	
		let {dayStart, dayEnd} = tsPeriodDays(periodDays);
		data.success.answer.forEach(function ({
			id, date_started, title, reference, description, project_name, fields, assignee_name, status, editable
		}) {
			let disable_edit = (editable === 0) ? "invisible" : "";
			
			const date_master = (!!fields['master_date']) ? parseInt(fields['master_date'], 10) : date_started;
			const time_started = timestampToTime(date_master);
			const warningDateClass = (date_master !== date_started) ? 'bg-warning' : '';
			
			const tr = document.createElement('tr');
			tr.classList.add('task-ticket-excel', hideTask({date_started, dayStart, dayEnd}));
			tr.dataset.task_id = id;
			tr.dataset.date_started = date_master;
			tr.innerHTML = `
				<td class="ticket-id" data-item_value="${project_name}" data-item_id="inputProjectExcel">${id}</td>
				<td class="ticket-date ${warningDateClass}" data-item_value="${timestampToDate(date_master, false)}" data-item_id="inputDate">${timestampToDate(date_master, false)} ${time_started}</td>
				<td class="ticket-name" data-item_value="${assignee_name}" data-item_id="inputName">${assignee_name}</td>
				<td class="ticket-title-table" data-item_value="${title}" data-item_id="inputTitle">${title}</td>
				<td class="ticket-reference" data-item_value="${reference}" data-item_id="inputReference">${reference}</td>
				<td class="ticket-capop" data-item_value="${fields['capop']}" data-item_id="inputCapOp">${fields['capop']}</td>
				<td class="ticket-otl" data-item_value="${fields['otl']}" data-item_id="inputOtl">${fields['otl']}</td>
				<td class="ticket-status" data-item_value="${status}" data-item_id="inputStatus">${status}</td>
				<td class="text-center" data-item_value="${time_started.substring(0, 2)}" data-item_id="inputTime">
					<a href="#" class="${disable_edit}"><img class="icon-edit icon-edit-sm" src="img/edit.svg"></a>
				</td>
				<td class="text-center" data-item_value="${description}" data-item_id="ticketDescriptionExcel">
					<a href="#" class="${disable_edit}"><img class="icon-delete icon-delete-sm" src="img/delete.svg"></a>
				</td>	
			`;
			excelDataArray.push(tr);
		});
		// dataTableExcel.rows.add(excelDataArray).draw();
		refreshBoardTable();
	};

	const showStatisticsTable = (data) => {
		dataTableStatistics.clear().draw();
		if (!!data.success) {
			data.success.answer.forEach(function ({project_name, title, url, date_creation, fields}) {
				const tr = document.createElement('tr');
				tr.innerHTML = `
					<td>${project_name}</td>
	 				<td>${timestampToDate(date_creation, false)}</td>
	 				<td>${fields.otl}</td>
	 				<td>${title}</td>
	 				<td>${fields.creator}</td>
	 				<td>
	 					<a href="${url}" class="text-decoration-none" target="_blank">Link</a>
	 				</td>
	 			`;
				dataTableStatistics.row.add(tr);
			});
			dataTableStatistics.draw();
		} else if (!!data.error) {
			containerError.innerText = data.error.error;
			containerError.classList.remove('d-none');
		}
	};

	const showStatusTable = (data) => {
		if (!!data.success) {
			data.success.answer.sort(byField('date_creation'));
			data.success.answer.forEach(function ({id, title, assignee_name, status, date_creation, date_started, reference, description, project_name, fields}) {
				const submitted_name = getField(fields, 'creator', '');
				const originTaskID = getField(fields, 'origintask', id);
				tableStatus.insertAdjacentHTML('beforeend', `
				 	<tr class="task-ticket-status" data-task_id="${id}" data-origin_id="${originTaskID}">
						<td>${id}</td>
						<td data-item_value="${title}" data-item_id="titleStatus">${title}</td>
						<td data-item_value="${submitted_name}" data-item_id="creatorStatus">${submitted_name}</td>
						<td data-item_value="${getOTL(fields)}" data-item_id="OTLStatus">${assignee_name}</td>
						<td>${timestampToDate(date_creation, false)}</td>
						<td>${timestampToDate(date_started, false)}</td>
						<td data-item_value="${description}" data-item_id="ticketDescriptionStatus">${reference}</td>
						<td data-item_value="${project_name}" data-item_id="inputProjectStatus">${status}</td>
						<td class="text-center">
							<a href="#"><img class="icon-edit icon-edit-sm" src="img/edit.svg"></a>
						</td>
					</tr>
				`);
			});
			
		} else if (!!data.error) {
			containerError.innerText = data.error.error;
			containerError.classList.remove('d-none');
		}
		$('#waitModal').modal('hide');
	};

	const showAutomator = (data) => {
		if(!!data.answer) {
			while (listDevices.hasChildNodes()) {   
				listDevices.removeChild(listDevices.firstChild);
			}
			const tableNode = document.createElement('table');
			const tbodyNode = document.createElement('tbody');
			const trNodeIDs = document.createElement('tr');
			const tdNodeBlank2 = document.createElement('td');
			tdNodeBlank2.innerHTML = '<input type="checkbox" id="main_checkbox" title="check/uncheck all">';
			trNodeIDs.appendChild(tdNodeBlank2);
			for (var cell_idx in data.answer[0]) // get names
			{
				const tdNodeIDs = document.createElement('td');
				tdNodeIDs.innerText = data.answer[0][cell_idx];
				trNodeIDs.appendChild(tdNodeIDs);
			}
			tbodyNode.appendChild(trNodeIDs);
			
			for (let row_idx in data.answer)
			{
				if (row_idx == 0) continue;
				for (let device_idx in data.answer[row_idx])
				{
					const trNodeValues = document.createElement('tr');
					const tdNodeCB = document.createElement('td');
					tdNodeCB.innerHTML = `<input type="checkbox" id="cb_${device_idx}" class="check_box" name="checked_devices">`;
					trNodeValues.appendChild(tdNodeCB);
					for (let cell_idx in data.answer[row_idx][device_idx])
					{
						const tdNodeValues = document.createElement('td');
						tdNodeValues.innerText = data.answer[row_idx][device_idx][cell_idx];
						trNodeValues.appendChild(tdNodeValues);
					}
					tbodyNode.appendChild(trNodeValues);	
				}
			}
			tableNode.appendChild(tbodyNode);
			tableNode.classList.add('table', 'table-bordered', 'table-hover', 'table-sm');
			listDevices.appendChild(tableNode);
			document.getElementById('main_checkbox').addEventListener('click', checkAllCB);
		}
	};

	const showTemplates = (data) => {
		if (!!data.answer) {
			const templArr = data.answer;
			listTemplates.textContent = '';
			listTemplates.insertAdjacentHTML('beforeend', `
				<ul class="list-group template-list">
					${templArr.reduce((res, current) => res + `
						<li class="list-group-item py-0 d-flex justify-content-between">
							<span class="">${current[1]}</span>
							<span class="">
								<a href="${encodeURI(current[2] + current[1])}" download class="template-link">
									<img class="icon-delete" src="img/download.svg">
								</a>
								<a href="#" class="template-link template-action" data-id="${current[0]}" data-action="delete" data-filename="${encodeURI(current[1])}">
									<img class="icon-delete" src="img/delete.svg">
								</a>
							</span
							
						</li>
					`,
					 '')}
				</ul>
			`);
		} else {
			errorMsgAutomator.innerText = JSON.stringify(data);
			errorMsgAutomator.style.display = 'block';
		}
	};

	const showModalDialog = ({attributes, dialogTitle, dialogQuestion, previousModal = null}, dialogModalProps = {selector:'#dialogModal', titleSelector:'#titleDialogModal', questionSelector: '#questionDialogModal'}) => {
		attributes.forEach(item => {
			for (const [attrName, attrValue] of Object.entries(item)) {
				modalCommand.setAttribute(attrName, attrValue);
			}
		});
		document.querySelector(dialogModalProps.titleSelector).innerText = dialogTitle;
		document.querySelector(dialogModalProps.questionSelector).innerText = dialogQuestion;
		if (previousModal) {
			$(previousModal).modal('hide');
		}
		$(dialogModalProps.selector).modal({
  			keyboard: true
		});
	};

	btnDevicesSelect.addEventListener('click', () => {
		if (typeof xls_files == 'undefined' || !xls_files) return false;
		uploadAutomatorItems({
			formParams: {
				doUploadDevices: 1,
				devicesFile: xls_files[0]
			},
			URL: automatorURL,
			callback: function () {
				$('#modalDevicesUploadDialog').modal('hide');
				getAutomator();
			}
		})
		xls_files = null;
	});

	document.querySelector('#templateFileUploadButton').addEventListener('change', (e) => 
	{
		const target = e.target;
		if (typeof target.files == 'undefined') return false;
			uploadAutomatorItems({
				formParams: {
					doUploadTemplate: 1,
					templateFile: target.files[0]
				},
				URL: automatorURL,
				callback: doGetTemplates,
			});
			target.value = '';
	});

	document.querySelector('#devicesFileUploadButton').addEventListener('change', function(e)
	{
		const target = e.target;
		xls_files = target.files;
	});

	document.querySelector('#deleteDevices').addEventListener('click', function()
	{
		modalCommand.setAttribute('modal-command', 'deleteDevices');
		document.getElementById('titleDialogModal').innerText = "Delete Device";
		document.getElementById('questionDialogModal').innerText = 'Do you want to delete selected devices?';
		$('#dialogModal').modal({
  			keyboard: true
		});
	});

	document.querySelector('#downloadTemplate').addEventListener('click', function()
	{
		const arr_id_checked = getArraySelectedCbox(listDevices.querySelectorAll('.check_box'), 'cb_');
		$('#dialogModal').modal('hide');

		if (arr_id_checked.length)
		{
			commonAutomatorRequest({
			formParams: {
				doDownloadTemplate: 1,
				devices_id: JSON.stringify(arr_id_checked),
			},
			URL: automatorURL,
			callback: function (data) {
				document.location.href = data.answer;
			}
		});
		}
	});

	modalCommand.addEventListener('click', (e) => {
		const target = e.target;
		const modalCommandValue = target.getAttribute('modal-command');
		$('#dialogModal').modal('hide');
		switch(modalCommandValue)
		{
			case 'deleteTemplate':
				commonAutomatorRequest({
					formParams: {
						doDeleteTemplates: 1,
						templates_id: JSON.stringify([target.getAttribute('data-id')]),
					},
					URL: automatorURL,
					callback: doGetTemplates,
				});
				break;
			case 'deleteDevices':
				commonAutomatorRequest({
					formParams: {
						doDeleteDevices: 1,
						devices_id: JSON.stringify(getArraySelectedCbox(listDevices.querySelectorAll('.check_box'), 'cb_')),
					},
					URL: automatorURL,
					callback: getAutomator,
				});
				break;
			default:
		}
	});

	const getOTL = (fieldsKanboard) => {
		let OTLStatus = '';
		if (!!fieldsKanboard['otl'] && fieldsKanboard['otl'] !== '') {
			OTLStatus = fieldsKanboard['otl'];
		}
		return OTLStatus;
	};


	const selectStatus = (e) => {
		e.preventDefault();
		const target = e.target;
		const selectElem = document.querySelector(`#${target.dataset.select_id}`);
		if (!!selectElem) {
			if (target.classList.contains('hold')) {
				target.classList.add('btn-secondary');
				target.classList.remove('btn-info', 'hold');

				selectElem.dataset.hold = '0';
			} else {
				target.classList.remove('btn-secondary');
				target.classList.add('btn-info', 'hold');

				selectElem.dataset.hold = '1';
			}
		}
	};

	const applyFilterTable = (e) => {
		const target = e.target;
		if (target.dataset.hold === '1') {
			target.dataset.select_value = target.value;
		} else 
		{
			target.dataset.select_value = '';
		}
		dataTableExcel.column('.column-status').search(target.value).draw();
	}

	const resetFilterTable = () => {
		dataTableExcel.column('.column-status').search('').draw();
	};

	$('#modalTemplateUploadDialog').on('show.bs.modal', function (e) {
		formTeplateUpload.reset();
		errorMsgAutomator.style.display = "none";
		errorMsgAutomator.innerText = '';
		doGetTemplates();
	});

	$('#modalDevicesUploadDialog').on('show.bs.modal', function (e) {
		formDevicesUpload.reset();
		errorMsgDevices.style.display="none";
		errorMsgDevices.innerText='';
	});
	
	function checkAllCB()
	{
		if(document.getElementById('main_checkbox').checked)
		{
			for (let check_boxes of listDevices.getElementsByClassName('check_box'))
			{
				check_boxes.checked = true;	
			}
		}
		else
		{
			for (let check_boxes of listDevices.getElementsByClassName('check_box'))
			{
				check_boxes.checked = false;
			}
		}
	}

	function getArraySelectedCbox (cboxNodes, cboxPrefix) {
		const arr_id_checked = [];
		const re = new RegExp(`${cboxPrefix}([0-9]+)`);
		for (let check_box of cboxNodes)
		{
			let match_id = check_box.id.match(re);
			if(match_id && check_box.checked)
			{
				arr_id_checked.push(match_id[1]);
			}
		}
		return arr_id_checked;
	}

	function uploadAutomatorItems ({ formParams, URL, callback = null }) {
		const formData = new FormData();
		for (let param in formParams) {
			formData.append(param, formParams[param]);	
		}
		sendFile('POST', URL, formData).then(() => {
			if (callback) {
				callback();
			}
		});
	}

	function commonAutomatorRequest ({ formParams, URL, callback = null }) {
		const formData = new FormData();
		for (let param in formParams) {
			formData.append(param, formParams[param]);	
		}
		sendRequestAutomator('POST', URL, formData).then((data) => {
			if (callback) {
				callback(data);
			}
		});
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

	function sendDataTask(dataForm, callback, additionalParams, updateMode = false) {
		const formData = new FormData(dataForm);
		const arrData = {};
		let method = 'createTask';
		if (updateMode === true) {
			method = 'updateTaskFull';
		}
		for (let [key, value] of formData.entries()) {
			if (typeof value == 'object') continue;
			arrData[key] = value.trim();
		}
		for (let prop in additionalParams) {
			arrData[prop] = additionalParams[prop];
		}
		const body = {
			method: method,
			params: arrData,
		};
		sendRequest('POST', requestURL, body).then(callback);
	}

	//init main
	btnClearSettings.addEventListener('click', clearEditableFields);
	ticketsContainer.addEventListener('click', actionTask);
	formNewTask.addEventListener('submit', (e) => {
		e.preventDefault();
		const element = document.activeElement;
		if (element.tagName === 'BUTTON') {
			let action = element.getAttribute('data-action');
			if (action === 'add') {
				createTask(showAddedTask);
			} else if (action === 'update') {
				updateTask();
			}
		}
		
	});
	formNewTask.addEventListener('reset', (e) => {
		ticketDescription.textContent = '';
		btnUpdateTask.classList.add('d-none');
		btnAddTask.classList.remove('d-none');
		attachmentsArea.classList.add('invisible');
		btnUpdateTask.dataset['task_id'] = 0;
		btnCreateTaskFile.removeAttribute('task_id');
		taskMain_id.value = 0;
	});

	btnCreateTaskFile.addEventListener('change', (e) => {
		createTaskFileNew(e, btnCreateTaskFile, attachmentsContainer);
	});

	[attachmentsContainer, attachmentsContainerStatus, attachmentsContainerExcel].forEach(item => {
			item.addEventListener('click', attachmentsAction);
		});
	
	btnCreateTaskFileStatus.addEventListener('change', (e) => {
		let callback = null;
		if (document.activeElement.type === 'submit') {
			callback = getTaskStatus;
		}
		createTaskFileNew(e, btnCreateTaskFileStatus, attachmentsContainerStatus, callback);
	});
		
	btnCreateTaskFileExcel.addEventListener('change', (e) => {
		let callback = null;
		if (document.activeElement.type === 'submit') {
			callback = getTaskBoard;
		}
		createTaskFileNew(e, btnCreateTaskFileExcel, attachmentsContainerExcel, callback);
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
	inputStatus.addEventListener('change', applyFilterTable);
	btnRemove.addEventListener('click', removeTask);
	holdStatus.addEventListener('click', selectStatus);

	ticketExcelForm.addEventListener('submit', (e) => {
		e.preventDefault();
		const element = document.activeElement;
		if (element.tagName === 'BUTTON') {
			let action = element.getAttribute('data-action');
			const date_start = new Date(`${inputDate.value}T${inputTime.value}:00`);
			if (action === 'add') {
				sendDataTask(e.target, attachFileExcel, {
					'description': ticketDescriptionExcel.innerText,
					'date_started': (isNaN(date_start)) ? 0 : date_start.getTime() / 1000,
				}, false);
			} else if (action === 'update') {
				sendDataTask(e.target, getTaskBoard, {
					'description': ticketDescriptionExcel.innerText,
					'date_started': (isNaN(date_start)) ? 0 : date_start.getTime() / 1000,
				}, true);
			}
		}
	});

	ticketExcelForm.addEventListener('reset', (e) => {
		let select_value = '';
		
		attachmentsContainerExcel.textContent = '';
		btnCreateTaskFileExcel.removeAttribute('task_id');
		taskExcel_id.value = 0;
		btnUpdateTaskExcel.dataset['task_id'] = 0;
		ticketDescriptionExcel.textContent = '';
		btnUpdateTaskExcel.classList.add('d-none');
		btnUpdateTaskExcel.disabled = true;
		btnAddTaskExcel.classList.remove('d-none');
		selectTR('.task-ticket-excel');

		if (inputStatus.dataset.hold === '1') {
			select_value = inputStatus.dataset.select_value;
		} else {
			resetFilterTable();
		}

	});

	inputTitle.addEventListener('blur', (e) => {
		if (inputStatus.dataset.hold === '1') {
			inputStatus.value = inputStatus.dataset.select_value;
		}
	});


	//init status
	tableStatus.addEventListener('click', editStatusTask);
	formNewTaskStatus.addEventListener('reset', (e) => {
		const target = e.target;
		attachmentsContainerStatus.textContent = '';
		target.querySelectorAll('[data-disable_on_update="1"]').forEach(item => {
			item.readOnly = false;
			item.classList.remove('text-muted');
		});
		target.removeEventListener('input', toggleFormStatusToNew);
		btnCreateTaskFileStatus.removeAttribute('task_id');
		taskStatus_id.value = 0;
		ticketDescriptionStatus.textContent = '';
		btnUpdateTaskStatus.classList.add('d-none');
		btnUpdateTaskStatus.disabled = true;
		btnAddTaskStatus.classList.remove('d-none');
		previousElem = null;
		selectTR('.task-ticket-status');
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
		sendRequest('POST', requestURL, body).then(attachFileStatus);
	});

	// init automator
	listTemplates.addEventListener('click', (e) => {
		const target = e.target.closest('.template-action');
		if (target) {
			e.preventDefault();
			const {action = '', id = 0, filename = ''} = target.dataset;
			switch (action) {
				case 'delete':
					showModalDialog({
						attributes: [
							{'modal-command': 'deleteTemplate'},
							{'data-id': id},
						],
						dialogTitle: 'Delete template',
						dialogQuestion: `Do you want to delete template ${decodeURI(filename)}?`,
					});
					break;
				default:
					break;
			}
			
		}
		
	})

	clearInputsForms();
	iniInterface();
	holdStatus.click();
});