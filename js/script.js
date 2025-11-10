'use strict';

const requestURL = 'backend.php',
	automatorURL = 'utils.php',
	requestURLTemplate = 'mop_admin.php',
    requestURLRender = 'mop_render.php',
	requestURLProject = "project_admin.php",
    inputSelectorClass = 'input-edit';

let wikiURL = '', wikiLDAPAuth = 0;

let gPrimeElementID = 0,
    gActivityID = 0,
	gActivityName = '',
	gCounterMode = "",
    adminEnabled = 1,
    totalInputs = 0,
    changedInputs = 0,
	templateDip = 0;
	// templateMop = 0;

const hardCodeDesign = {
	'Add/Change/Remove Roaming': 'js-eFCR-view',
	'Add/Change/Remove': 'js-eFCR2-view',
	'Capacity Upgrade': 'js-cSDEPingTest-view',
};
const sectionChildren = {
	'dip': {
		'Roaming FCR': 
		[
			'Firewall',
			'Add/Change/Remove Roaming',
			'fcr'
		],
		'eFCR': [
			'Firewall',
			'Add/Change/Remove',
			'efcr',
		],
	},
	'cSDEPingtest': [
		'AGW / DGW',
		'Ping Test',
		'pingtest',
	],
	'cSDEBundle': [
		'AGW / DGW',
		'Bundle',
		'bundle',
	],
};

const documentProperties = {
	'cSDEPingtest': {
		title: 'cSDE Ping Test',
		csde_type: 'pingtest',
	},
	'cSDEBundle': {
		title: 'cSDE Bundle',
		csde_type: 'bundle',
	},
	csde_type: 'pingtest',
	legend: 'cSDE Ping Test',
};

// const cTemplateGroups = {
// 	0: "RHSI and Service Delivery Environment",
// 	1: "IP Core",
// };
let cTemplate = 0;
const subMenuClass = 'children-menu';

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
	'services',
	'template',
	'mop',
	'ctemplate',
	'cmop',
	'template DIP',
	'dip',
	'template cDIP',
	'cdip',
	'capacity',
	'cSDEPingtest',
	'cSDEBundle',
	'inventory',
	'projects',
	// 'documentation',
	'action',
];


const defaultUserRights = [];
const ProjectUsersList = [];
const groupsUsersList = [];
const groupsListInProjects = [];

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
let no_virt = 1;

let dayStartExcel, dayEndExcel = 0;

const table_statistics_selector = 'table_statistics',
	exportStatistics_selector = 'exportStatistics',
	devicesMosaic_selector = 'devicesMosaic',
	exportMosaic_selector = 'exportMosaic';

const table_excel_selector = 'table_excel';
const exportExcel_selector = 'exportExcel';
const mosaicTableSelector = 'mosaic-table-row';
const inventoryTableSelector = 'inventory-table-row';

const excelDataArray = [];

const selectCnange = new Event('change');

const errorMsg = document.createElement('div');
errorMsg.textContent = 'Username or password is incorrect';

let filesTemplate, xls_files;

//documentation section
let savedPageName = 'New Page',
	page_id = '0';

const inventoryTagsSet = new Set();
let inventoryMode = 0, efcrMode = 0;

// common functions
// for sort in ORDER DESC by default
const byField = (field, asc = false) => {
	let min = -1, max = 1;
	if (asc) {
		min = 1; max = -1;
	}
	return (a, b) => a[field] > b[field] ? min : max;
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

const cr2br = data_str => {
	return String(data_str).replace(/\n/g, '<br>');
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

function b64EncodeUnicode(str) {
    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g,
        function toSolidBytes(match, p1) {
            return String.fromCharCode('0x' + p1);
    }));
}

function b64DecodeUnicode(str) {
    return decodeURIComponent(atob(str).split('').map(function(c) {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
}

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
		cacheForm = document.querySelector('#cache_form'),
		btnAddUser = document.querySelector('#btnAddUser'),
		groupsList = document.querySelector('#groups-list'),
		groupUsers = document.querySelector('#group-users'),
		allUsers = document.querySelector('#all-users'),
		addUserGroup = document.querySelector('#addUserGroup'),
		removeUserFromGroup = document.querySelector('#deleteUserGroup'),
		newUsernameInput = document.querySelector('#newUsername'),
		newPasswordInput = document.querySelector('#newPassword'),
		rightsUserName = document.querySelector('#rightsUserName'),
		rightsForm = document.querySelector('#rights-form');
	// projects elements
	const groupsListProjects = document.querySelector('#group-project'),
		btnGroupAppend = document.querySelector('.js-group-append'),
		btnGroupRemove = document.querySelector('.js-group-remove'),
		projectNumber = document.querySelector('#projectNumberVal'),
		projectName = document.querySelector('#projectNameVal'),
		projectText = document.querySelector('#projectTextVal'),
		projectsList = document.querySelector('#projects-list'),
		// projectSubmit = document.querySelector('#projectSubmit'),

		projectGroups = document.querySelector('#project-groups');
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
		setRightsContainer = document.querySelector('.set-rights'),
		tokensContainer = document.querySelector('.documentation-tokens');
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
	// request elements
	const formNewTaskStatus = document.querySelector('#formNewTaskStatus'),
		btnUpdateTaskStatus = document.querySelector('.btn-update-task-status'),
		btnAddTaskStatus = document.querySelector('.btn-add-task-status'),
		ticketDescriptionStatus = document.querySelector('#ticketDescriptionStatus'),
		btnCreateTaskFileStatus = document.querySelector('#attachFileStatus'),
		attachmentsContainerStatus = document.querySelector('.attachments-container-status'),
		ticketProjectNameStatus = document.querySelector('#inputProjectStatus'),
		inputGroupRequest = document.querySelector('#inputGroupRequest'),
		taskStatus_id = document.querySelector('#taskStatus_id'),
		attachmentsAreaStatus = document.querySelector('.attachments-area-status'),
		tableStatus = document.querySelector('.table-request'),
		ticketCreatorStatus = document.querySelector('#creatorStatus'),
		// creatorApply = document.querySelector('#creatorApply'),
		ticketOTLStatus = document.querySelector('#OTLStatus');
	// statistics elements

	// automator elements
	const listDevices = document.querySelector('#listDevices'),
		listTemplates = document.querySelector('#uploadedTemplates'),
		formTeplateUpload = document.querySelector('#formTeplateUpload'),
		formDevicesUpload=document.querySelector('#formDevicesUpload'),
		errorMsgAutomator = document.querySelector('#upload_error'),
		errorMsgDevices = document.querySelector('#upload_devices_error'),
		modalCommand = document.querySelector('#btnDialogModal'),
		btnDevicesSelect = document.querySelector('#buttonDevicesSelect');
	// mosaic elements
	const mainContainer = document.querySelector('.main-mosaic-container'),
		modifyContainer = mainContainer.querySelector('.modify-mosaic-container'),
		mosaicForm = modifyContainer.querySelector('#mosaicForm'),
		mosaicFormLoadData = document.querySelector('#mosaicFormLoadData'),
		// dividerArrow = mainContainer.querySelector('.divider-arrow'),
		devicesAllBody = mainContainer.querySelector('.devices-all-body'),
		titleDialogModal = document.querySelector('#titleDialogModal'),
		questionDialogModal = document.querySelector('#questionDialogModal'),
		loadExcelData = document.querySelector('#loadExcelData'),
		btnClearData = document.querySelector('#btnClearData'),
		// triangle = document.querySelector('.triangle'),
		// btnApplySettings = document.querySelector('.btn-apply-settings'),
		btnDialogModal = document.querySelector('#btnDialogModal');
	// documentation elements
	const pageNameEdit = document.querySelector('.page-name-edit'),
		newAttachment = document.querySelector('.btn_new-attachment'),
		bookAttachmentsList = document.querySelector('.attachments-list'),
		formAddAttachment = document.querySelector('#formAddAttachment'),
		formSearch = document.querySelector('#formSearch'),
		booksList = document.querySelector('.books-list'),
		pagesList = document.querySelector('.pages-list'),
		booksDiv = document.querySelector('.books'),
		pagesDiv = document.querySelector('.pages'),
		newPage = document.querySelector('.btn_new-page'),
		newBook = document.querySelector('.btn_new-book'),
		showBooksDiv = document.querySelector('.show-books'),
		showBooks = document.querySelector('.btn_show-books'),
		pagesTitle = pagesDiv.querySelector('h2'),
		searchContainer = document.querySelector('.search-result');

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
        divCounter = document.querySelector('.counter-pb'),
		renderMopDiv = document.querySelector('#render_mop'),
		docTitle = document.querySelector('#docTitle'),
        showAll = document.querySelector('#showAll'),
		efcrFields = document.querySelector('#efcrFields'),
		comboSelect = document.querySelectorAll('.combo-select'),
		btnsCeilAreaAppend = document.querySelectorAll('.js-ceil-area-append'),
		// btnsCeilAreaClone = document.querySelectorAll('.js-ceil-area-clone'),
		btnsCeilAreaRemove = document.querySelectorAll('.js-ceil-area-remove'),
		exportDownload = document.querySelector('.js-export-download'),
		impactedNCT = document.querySelector('#impactedNCT'),
		cSDEType = document.querySelector('#cSDEType'),
		visibleSuperOnly = document.querySelectorAll('.js-superOnly');
		// aExport = document.querySelector('#a_export'),
		// aImport = document.querySelector('#importFileJSON');

		// inventory elements
	const tableInventory = document.querySelector('#table-inventory'),
		inventoryFormLoadData = document.querySelector('#inventoryFormLoadData'),
		tableParts = document.querySelector('#table-parts'),
		tableTags = document.querySelector('#table-tags'),
		// bntNewChassis = document.querySelector('#table-tags'),
		inventoryTags = document.querySelector('.inventory-tags'),
		loadInventory = document.querySelector('#loadInventory'),
		inventoryComments = document.querySelector('#inventoryComments'),
		formInventoryComments = document.querySelector('#form_inventory_comments'),
		commentsText = document.querySelector('#commentsText'),
		btnCommentsModal = document.querySelector('#btnCommentsModal'),
		tInventory = document.querySelector('.t-inventory');

	const formEFCR = document.querySelector('#form-efcr'),
		formEFCRExport = document.querySelector('#form-efcrExport'),
		loadEFCR = document.querySelector('#loadEFCR');
		// tEFCR = document.querySelector('.t-efcr');

	const bundleLink = document.querySelector('.bundle-link');

		btnNewActivity.dataset.prime_elem_id = 0;



	let showMosaicEditItems = localStorage.getItem('showMosaicEditItems');
	if (!showMosaicEditItems) {
		showMosaicEditItems = '0';
	}

	const displayMOPElements = (adminView = false) => {
		adminEnabled = adminView;
		if (adminView) {
			adminViewElems.forEach((elem) => {
				if (!elem.classList.contains('js-superOnly') || 
				(currentUser === 'super' && elem.classList.contains('js-superOnly'))) {
					setAvailFormElements(elem, false);
				}
			});
			formSubmit.innerText = 'Apply';
		} else {
			adminViewElems.forEach((elem) => {
				setAvailFormElements(elem, true);
			});
			formSubmit.innerText = 'Create';
		}
	};

	// const fillEFCR = (dataTableE, item) => {
	// 	dataTableE.row.add(item);
	// };

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
				const btnExcel = document.querySelector(`.native-excel`);
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
					text: 'Today',paging: false,
					searching: true,
					info: false,
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
				{
					extend: "excel",
					text: '',
					title: null,
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

	const mosaicTable = new DataTable(`#${devicesMosaic_selector}`, {
        ajax: {
            url: 'backend.php',
            data: function (d) {
                d.env = 'services';
			    d.call = 'doGetDevicesAll';
				d.no_virt = no_virt;
            }
        },
		dom: '<"mosaic-menu"B<"table-controls"plfr>ti>',
        columns: [							
            { data: 'node', "name": "name", render: function (data, type, row, meta) {
                return `<span class="name-text">${data}</span>`;
            }, "width": "8%" },
            { data: 'interface', "name": "port", "width": "8%" },
            { data: 'description', "name": "descr", "width": "35%" },
            { data: 'platform', "name": "platform", render: function (data, type, row, meta) {
                return `<span class="name-text editable" data-name="platform" data-value="${data}">${data}</span>`;
            }, "width": "8%" },
            { data: 'tag', "name": "tags", render: function (data, type, row, meta) {
                return `<span class="name-text editable" data-name="tags" data-value="${data}">${data}</span>`;
            }, "width": "9%"},
            { data: 'group', "name": "group_name", render: function (data, type, row, meta) {
                return `<span class="name-text editable" data-name="group" data-value="${data}">${data}</span>`;
            }, "width": "10%" },
            { data: 'owner', "name": "manager", render: function (data, type, row, meta) {
                return `<span class="name-text editable" data-name="owner" data-value="${data}">${data}</span>`;
            }, "width": "10%" },
            { data: 'note', "searchable": false, "orderable": false,
				render: function (data, type, row, meta) {
                return `<span class="name-text editable" data-name="comments" data-value="${data}">${data}</span>`;
            }, "width": "7%" },
            { data: null , "searchable": false, "orderable": false,
				defaultContent: `
                <div class="action-buttons justify-content-center d-block d-xl-flex">
                    <a href="#" data-locked='1'>
                        <img class="icon-edit icon-edit-sm" data-edit src="img/edit.svg">
                        <img class="icon icon-edit-sm hidden" data-undo src="img/undo.svg" title="Undo">
                        <img class="icon icon-edit-sm hidden" data-done src="img/done.svg" title="Done">
                        <img class="icon icon-edit-sm hidden" data-lock src="img/lock.svg" title="Switch to All">
                        <img class="icon icon-edit-sm hidden" data-open src="img/lock_open.svg" title="Switch to one">
                    </a>
                    <a href="#"><img class="icon-delete icon-delete-sm" src="img/delete.svg"></a>
                </div>`,
            "width": "10%" },
        ],
		buttons: 
			{
				dom: {
					container: {
						tag: 'div',
						className: 'mosaic-buttons'
					},
					button: {
						tag: 'button',
						className: []
					},
					buttonLiner: {
						tag: null
					}

				},
				buttons: [
				{
					extend: "excel",
					text: 'Export Data',
					title: null,
					className: 'mosaic-excel btn-devices btn-devices-export',
					filename: '* Export',
					attr: {
						title: 'Export to Excel',
						id: exportMosaic_selector
					},
					tag: 'a',
					exportOptions: {
						// columns: '.exportable'
						columns: [0, 1, 2, 3, 5, 6, 7],
					}
				},
				{
					tag: 'label',
					text: 'Import Data',
					className: 'btn-devices',
					attr: {
						for: 'loadExcelData',
						id: 'btnLoadExcelData',
					},
					action: function (e, dt, node, config) {
						const target = e.target;
						document.querySelector(`#${target.getAttribute('for')}`).click();
					}
				},
				{
					tag: 'label',
					className: 'switch switch-services',
					collectionLayout: 'fixed',
					text: '<input type="checkbox" id="virt"><span class="slider round"></span>',
					action: function (e, dt, node, config) {
						const target = e.target;
						const virt = target.closest('label').querySelector('#virt');
						if (virt.checked == true) {
							virt.checked = false;
							no_virt = 1;
						} else {
							virt.checked = true;
							no_virt = 0;
						}
						mosaicTable.ajax.reload();
					}
				},
				{
					tag: 'span',
					text: ' Virtual int',
				}
			]
		},
        lengthMenu: [[100, 200, -1],[100, 200, "All"]],
        pageLength: 100,
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        search: {
            return: true
        },
		autoWidth: false,
    });

	const createInventoryFilter = (filterSelector,filterType) => {
		const filter = new Set();
		document.querySelectorAll(filterSelector).forEach(item => {
			if (item.dataset.checked == "1") {
				filter.add(item.dataset[filterType]);
			}
		});
		const filter_str = [...filter].join(';');
		return filter_str;
	};

	const dataTableInventory = $(`#table-inventory`)
		.on('preXhr.dt', function (e, settings, data) {
			data.get_data = inventoryMode;
			data.vendor_filter = createInventoryFilter('.filter-one.js-vendors', 'vendor');
			data.date_filter = createInventoryFilter('.filter-one.js-date', 'year');
		})
		.DataTable({
			ajax: {
				url: 'backend.php',
				data: function (d) {
					d.env = 'services';
					d.call = 'doGetInventory';
				}
			},
			dom: '<"mosaic-menu"B<"table-controls"plfr>ti>',
			columns: [							
				{ data: 'node', "name": "node_name", render: function (data, type, row, meta) {
					return `<span class="name-text">${data}</span>`;
				}, "width": "14%" },
				{ data: 'vendor', "name": "vendor", "width": "6%" },
				{ data: 'hw_model', "name": "hw_model", "width": "8%" },
				{ data: 'serial', "name": "serial", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="serial" data-value="${data}">${data}</span>`;
				}, "width": "12%"},
				{ data: 'software', "name": "software", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="software" data-value="${data}">${data}</span>`;
				}, "width": "6%" },
				{ data: 'hw_eos', "name": "hw_eos", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="hw_eos" data-value="${data}">${data}</span>`;
				}, "width": "6%" },
				{ data: 'hw_eol', "name": "hw_eol", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="hw_eol" data-value="${data}">${data}</span>`;
				}, "width": "6%" },
				{ data: 'sw_eos', "name": "sw_eos", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="sw_eos" data-value="${data}">${data}</span>`;
				}, "width": "6%" },
				{ data: 'sw_eol', "name": "sw_eol", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="sw_eol" data-value="${data}">${data}</span>`;
				}, "width": "6%" },
				{ data: 'ca_year', "name": "ca_year", render: function (data, type, row, meta) {
					return `<span class="name-text editable" data-name="ca_year" data-value="${data}">${data}</span>`;
				}, "width": "6%" },
				{ data: 'comments', "searchable": false, "orderable": false,
					render: function (data, type, row, meta) {
						let comments = '';
						data.forEach(item => {
							comments = item.comment;
						});
					return `<div class="comments-brief"><span class="name-text" data-name="comments" data-value="${comments}">${comments}</span><a href="#">&nbsp;</a></div>`;
				}, "width": "14%" },
				{ data: null , "searchable": false, "orderable": false,
					defaultContent: `
					<div class="action-buttons justify-content-center d-block d-xl-flex">
						<a href="#" data-locked='1'>
							<img class="icon-edit icon-edit-sm" data-edit src="img/edit.svg">
							<img class="icon icon-edit-sm hidden" data-undo src="img/undo.svg" title="Undo">
							<img class="icon icon-edit-sm hidden" data-done src="img/done.svg" title="Done">
							<img class="icon icon-edit-sm hidden" data-lock src="img/lock.svg" title="Switch to All">
							<img class="icon icon-edit-sm hidden" data-open src="img/lock_open.svg" title="Switch to one">
						</a>
						<a href="#"><img class="icon-delete icon-delete-sm" src="img/delete.svg"></a>
					</div>`,
				"width": "10%" },
			],
			buttons: 
			{
				dom: {
					container: {
						tag: 'div',
						className: 'mosaic-buttons'
					},
					button: {
						tag: 'button',
						className: []
					},
					buttonLiner: {
						tag: null
					}

				},
				buttons: [
					{
						extend: "excel",
						text: 'Export Data',
						title: null,
						className: 'mosaic-excel btn-devices btn-devices-export',
						filename: '* Export',
						attr: {
							title: 'Export to Excel',
							id: exportMosaic_selector
						},
						tag: 'a',
						exportOptions: {
							columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
						}
					},
					{
						tag: 'label',
						text: 'Import Data',
						className: 'btn-devices',
						attr: {
							for: 'loadInventory',
							id: 'btnLoadloadInventory',
						},
						action: function (e, dt, node, config) {
							const target = e.target;
							document.querySelector(`#${target.getAttribute('for')}`).click();
						}
					},
					{
						tag: 'div',
						className: 'inventory-filters',
						action: function (e, dt, node, config) {
							const target = e.target;
							e.preventDefault();
							if (target.tagName === 'BUTTON') {
								const list = document.querySelector('.inventory-filters-list');
								if (list.classList.contains('hidden')) {
									list.classList.remove('hidden');
								} else {
									list.classList.add('hidden');
								}
							} else if (target.closest('.filter-one')) {
								const parent_p = target.closest('.filter-one');
								if (parent_p.dataset.checked == "1") {
									parent_p.dataset.checked = "0";
									parent_p.querySelector('.js-filter-on').classList.add('hidden');
									parent_p.querySelector('.js-filter-off').classList.remove('hidden');
								} else {
									parent_p.dataset.checked = "1";
									parent_p.querySelector('.js-filter-on').classList.remove('hidden');
									parent_p.querySelector('.js-filter-off').classList.add('hidden');
								}
								this.draw();
							}
						},
						text: `<button class="btn-devices">Vendors</button>
							<div class="inventory-filters-list hidden">
								<p class="filter-one js-vendors" data-vendor="cisco" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on " src="img/check_box.svg">
									Cisco
								</p>
								<p class="filter-one js-vendors" data-vendor="juniper" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on " src="img/check_box.svg">
									Juniper
								</p>
								<p class="filter-one js-vendors" data-vendor="a10" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on " src="img/check_box.svg">
									A10
								</p>
							</div>
						`
					},
					{
						tag: 'div',
						className: 'inventory-filters',
						action: function (e, dt, node, config) {
							const target = e.target;
							e.preventDefault();
							if (target.tagName === 'BUTTON') {
								const list = target.closest('.inventory-filters').querySelector('.inventory-filters-list');
								if (list.classList.contains('hidden')) {
									list.classList.remove('hidden');
								} else {
									list.classList.add('hidden');
								}
							} else if (target.closest('.filter-one')) {
								const parent_p = target.closest('.filter-one');
								if (parent_p.dataset.checked == "1") {
									parent_p.dataset.checked = "0";
									parent_p.querySelector('.js-filter-on').classList.add('hidden');
									parent_p.querySelector('.js-filter-off').classList.remove('hidden');
								} else {
									parent_p.dataset.checked = "1";
									parent_p.querySelector('.js-filter-on').classList.remove('hidden');
									parent_p.querySelector('.js-filter-off').classList.add('hidden');
								}
								this.draw();
							}
						},
						text: `<button class="btn-devices">Year</button>
							<div class="inventory-filters-list hidden">
								<p class="filter-one js-date" data-year="2022" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2022
								</p>
								<p class="filter-one js-date" data-year="2023" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2023
								</p>
								<p class="filter-one js-date" data-year="2024" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2024
								</p>
								<p class="filter-one js-date" data-year="2025" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2025
								</p>
								<p class="filter-one js-date" data-year="2026" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2026
								</p>
								<p class="filter-one js-date" data-year="2027" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2027
								</p>
								<p class="filter-one js-date" data-year="2028" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2028
								</p>
								<p class="filter-one js-date" data-year="2029" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2029
								</p>
								<p class="filter-one js-date" data-year="2030" data-checked="1">
									<img class="icon icon-edit-sm js-filter-off hidden" src="img/check_box_outline.svg">
									<img class="icon icon-edit-sm js-filter-on" src="img/check_box.svg">
									2030
								</p>
							</div>
						`
					}
				]
			},
			lengthMenu: [[100, 200, -1],[100, 200, "All"]],
			pageLength: 100,
			processing: true,
			serverSide: true,
			serverMethod: "POST",
			search: {
				return: true
			},
			autoWidth: false,
		});

	// const dataTableEFCR = $(`#table-efcr`)
	// .on('preXhr.dt', function (e, settings, data) {
	// 	data.get_data = efcrMode;
	// })
	// .DataTable({
	// 	dom: '<"mosaic-menu"B<"table-controls"plfr>ti>',
	// 	paging: false,
	// 	searching: false,
	// 	ordering: false,
	// 	info: false,
	// 	autoWidth: false,
	// 	columns: [
	// 		{ data: 'eFCRnumber', "name": "eFCR Number", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "15%"},
	// 		{ data: 'policyName', "name": "Policy Name", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "15%"},
	// 		{ data: 'sourceZone', "name": "Source Zone", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "10%"},
	// 		{ data: 'sourceSubnet', "name": "Source subnet", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "10%"},
	// 		{ data: 'destinationZone', "name": "Destination Zone", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "10%"},
	// 		{ data: 'destinationSubnet', "name": "Destination Subnet", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "10%"},
	// 		{ data: 'protocol', "name": "Protocol", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "8%"},
	// 		{ data: 'port', "name": "Port", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		},
	// 		"width": "4%"},
	// 		{ data: 'PHUBSites', "name": "PHUB sites", render: function (data, type, row, meta) {
	// 			if (data === undefined) { data = '&nbsp;'}
	// 			return `<span class="name-text editable new-tag" contenteditable>${data}</span>`;
	// 		}, "width": "10%"},
	// 		{ data: null , "searchable": false, "orderable": false,
	// 				defaultContent: `
	// 				<div class="action-buttons justify-content-center d-block d-xl-flex">
	// 					<a href="#" data-locked='1'>
	// 						<img class="icon-edit icon-edit-sm" data-add src="img/add_circle.svg">
	// 						<img class="icon-edit icon-edit-sm" data-add-copy src="img/add_task.svg">
	// 						<img class="icon icon-edit-sm hidden" data-undo src="img/undo.svg" title="Undo">
	// 						<img class="icon icon-edit-sm hidden" data-done src="img/done.svg" title="Done">
	// 					</a>
	// 					<a href="#"><img class="icon-delete icon-delete-sm" data-delete src="img/delete.svg"></a>
	// 				</div>`,
	// 				"width": "8%"
	// 		},
	// 	],
	// 	buttons: {
	// 		dom: {
	// 			container: {
	// 				tag: 'div',
	// 				className: 'mosaic-buttons'
	// 			},
	// 			button: {
	// 				tag: 'button',
	// 				className: []
	// 			},
	// 			buttonLiner: {
	// 				tag: null
	// 			}

	// 		},
	// 		buttons: [
	// 			{
	// 				tag: 'label',
	// 				text: 'Import Data',
	// 				className: 'btn-devices',
	// 				attr: {
	// 					for: 'loadEFCR',
	// 					id: 'btnEFCR',
	// 				},
	// 				action: function (e, dt, node, config) {
	// 					const target = e.target;
	// 					const input = target.getAttribute('for');
	// 					document.querySelector(`#${input}`).click();
	// 				}
	// 			},
	// 			{
	// 				tag: 'label',
	// 				text: 'Export Data',
	// 				className: 'btn-devices',
	// 				attr: {
	// 					id: 'btnEFCRExport',
	// 				},
	// 				action: function (e, dt, node, config) {
	// 					const json_table = [];
	// 					dt.rows().every( function() {
	// 						const row = this.data();
	// 						json_table.push(row);
	// 					});

	// 					formEFCRExport.reset();
	// 					formEFCRExport.querySelector('input[name="env"]').setAttribute('value', 'services');
	// 					formEFCRExport.querySelector('input[name="call"]').setAttribute('value', 'exportEFCR');
	// 					formEFCRExport.querySelector('input[name="efcrTable"]').setAttribute('value', JSON.stringify(json_table));
	// 					formEFCRExport.requestSubmit();				
	// 				}
	// 			}
	// 		],
	// 	},
	// });

	// dataTableEFCR.row.add({}).draw();


	const saveContent = () => {
		const savedName = (pageNameEdit.innerText.trim() !== ''  ? pageNameEdit.innerText.trim() : prompt('Enter the Page Name', savedPageName));

		if (savedName) {
			const body = {
				env: 'documentation',
				book_id: newPage.dataset.book_id,
				id: page_id,
				action: 'savePage',
				name: savedName,
				html: tinymce.activeEditor.getContent(),
			}
			sendRequest('POST', requestURL, body).then(showPageContent);
		}
	};

	const getBooksList = () => {
		window.open(
			`${wikiURL}/autologin.html?site=${b64EncodeUnicode(
				location.origin + location.pathname + requestURL
			)}&s=${b64EncodeUnicode(document.cookie)}&ldap=${wikiLDAPAuth}`,
			"_blank"
		);
	};

	const getBook = (id) => 
	{
		if (id != +id) return false;
		const body = {
			env: 'documentation',
			innerMethod: 'GET',
			action: `books/${id}`
		}
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer) {
				setBookTitle(data.success.answer.name, data.success.answer.id, pagesTitle);
			}
		});
	};

	const getPageList = book_id => {
		if (book_id != +book_id) return false;
		const body = {
			env: 'documentation',
			innerMethod: 'GET',
			action: `pages?filter[book_id]=${book_id}`
		}
		newPage.dataset.book_id = book_id;
		sendRequest('POST', requestURL, body).then((data) => {
			showItemList(data, pagesList, ['id', 'book_id']);
		});
	};

	const getPageContent = page_id => {
		if (page_id != +page_id) return false;
		const body = {
			env: 'documentation',
			innerMethod: 'GET',
			action: `pages/${page_id}`
		}
		sendRequest('POST', requestURL, body).then(showPageContent);
	};

	const getAttachmentsList = page_id => {
		if (page_id != +page_id) return false;
		const body = {
			env: 'documentation',
			innerMethod: 'GET',
			action: `attachments?filter[uploaded_to]=${page_id}`
		}
		newAttachment.dataset.page_id = page_id;
		newAttachment.style.display = '';
		sendRequest('POST', requestURL, body).then((data) => {
			showItemList(data, bookAttachmentsList, ['id', 'uploaded_to']);
		});
	};

	const downloadAttachment = id => {
		if (id != +id) return false;
		const body = {
			env: 'documentation',
			innerMethod: 'GET',
			action: `attachments/${id}`
		}
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer && data.success.answer.links) {
				try {
						const a = document.createElement('span');
						a.insertAdjacentHTML('beforeend', data.success.answer.links.html);
						window.open(a.querySelector('a').href);
				} catch (e) {
					tinymce.activeEditor.notificationManager.open({
						text: `An error occurred. Problem downloading file from link: ${data.success.answer.links.html}`,
						type: 'error'
					});
				}
			}
		});
	};

	const showSearchResult = (data) => {
		searchContainer.textContent = '';
		if (data && data.success && data.success.answer) {
			data.success.answer.data.forEach(item => {
				searchContainer.insertAdjacentHTML('beforeEnd', `
					<div class="search-result-item" data-id="${item.id}" data-type="${item.type}" data-book_id="${item.book_id ? item.book_id : '0'}">
						<h3><span class="item-name">${item.name}</span> - <span class="search-type">${item.type}</span></h3>
						<div class="preview-html">${item.preview_html.content}</div>
						<a href="${item.url}" target="_blank" class="book-url" title="Open in Bookstack">${item.preview_html.name}</a>
					</div>
				`);
			});
			if (data.success.answer.total != 0) {
				searchContainer.style.display = '';
			}
		}
	};

	const deleteItem = ({action, item, itemName, id, callback}) => {
		tinymce.activeEditor.windowManager.confirm(`Do you want delete ${itemName}?`, (state) => {
			if (state) {
				const body = {
					env: 'documentation',
					id: id,
					action: action,
					item: item,
				}
			sendRequest('POST', requestURL, body).then(callback);
			}
		});
	};

	const saveBook = (bookName, id = 0) => {
		const name = bookName.replace(/\n/g, " ").trim();
		
		if (name == '') return false;

		const body = {
			env: 'documentation',
			action: 'saveBook',
			id: id,
			name: name.replace(/\n/g, ""),
		}
		sendRequest('POST', requestURL, body).then(() => {
			getBooksList();
			if (id) {
				getBook(id);
			}
		});
	};

	newBook.addEventListener('click', (e) => {
		e.preventDefault();
		const nameNewBook = prompt('Enter the name for new book', '');
		if (nameNewBook) {
			saveBook(nameNewBook);
		}
	});

	newPage.addEventListener('click', (e) => {
		e.preventDefault();
		clearPageContent();
	});

	newAttachment.addEventListener('click', (e) => {
		e.preventDefault();
		if (newAttachment.dataset.page_id && newAttachment.dataset.page_id != '0') {
			bookFileAttachment.click();
		}
	});

	bookFileAttachment.addEventListener('change', (e) => {
		e.preventDefault();
		const target = e.target;
		bookFileName.value = target.files[0].name;
		uploaded_to_page.value = newAttachment.dataset.page_id;
		formAddAttachment.requestSubmit();
	});

	formSearch.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		let paramStr = '';
		for (let [key, value] of formData.entries()) {
			if (value.trim() != '') {
				paramStr += `${key}=${value.trim()}&`;
			}
		}
		const body = {
			env: 'documentation',
			innerMethod: 'GET',
			action: `search?${paramStr}`
		};
		if (paramStr != '') {
			searchContainer.style.display = 'none';
			sendRequest('POST', requestURL, body).then(showSearchResult);
		}
	});

	formAddAttachment.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		sendPostFile(requestURL, formData).then((data) => {
			if (data && data.success && data.success.answer) {
				getAttachmentsList(data.success.answer.uploaded_to);
			}
		});
	});

	const switchToPageView = (bookName = null, book_id = null) => {
		booksDiv.classList.add('d-none');
		pagesDiv.classList.remove('d-none');
		showBooksDiv.classList.remove('d-none');
		setBookTitle(bookName, book_id, pagesTitle);
	};

	searchContainer.addEventListener('click', (e) => {
		e.preventDefault();
		const target = e.target;
		const divSearch = target.closest('.search-result-item');
		if (target.closest('.book-url')) {
			window.open(target.closest('.book-url').href, '_blank');
			return true;
		}
		if (divSearch) {
			switch (divSearch.dataset.type) {
				case 'page':
					getPageContent(divSearch.dataset.id);
					getBook(divSearch.dataset.book_id);
					switchToPageView();
					break;
				case 'book':
					getPageList(divSearch.dataset.id);
					clearPageContent({
						bookName: divSearch.querySelector('.item-name').innerText,
						id: divSearch.dataset.id
					});
					break;
				default:
					break;
			}
			
		}
	});

	showBooks.addEventListener('click', (e) => {
		e.preventDefault();
		newPage.dataset.book_id = '0';
		booksDiv.classList.remove('d-none');
		pagesDiv.classList.add('d-none');
		showBooksDiv.classList.add('d-none');
		pagesTitle.innerText = '';
		clearPageContent();
	});

	booksList.addEventListener('click', (e) => {
		const target = e.target;
		const parent = target.closest('li');
		if (parent) {
			const itemName = parent.querySelector('.list-item-name').textContent;
			if (target.classList.contains('list-item-name')) {
				switchToPageView(itemName, parent.dataset.id);
				getPageList(parent.dataset.id);
			} else if (target.classList.contains('btn_delete')) {
				deleteItem({
					action: 'delete',
					item: 'books',
					itemName: itemName,
					id: parent.dataset.id,
					callback: getBooksList
				});
			}
		}
	});

	pagesList.addEventListener('click', (e) => {
		const target = e.target;
		const parent = target.closest('li');
		if (parent) {
			const itemName = parent.querySelector('.list-item-name').textContent;
			if (target.classList.contains('list-item-name')) {
				getPageContent(parent.dataset.id);
			}
			else if (target.classList.contains('btn_delete')) {
				deleteItem({
					action: 'delete',
					item: 'pages',
					itemName: itemName,
					id: parent.dataset.id,
					callback: () => {
						getPageList(newPage.dataset.book_id);
						clearPageContent();
					}
				});
			}
		}
	});

	bookAttachmentsList.addEventListener('click', (e) => {
		const target = e.target;
		const parent = target.closest('li');
		if (parent) {
			const itemName = parent.querySelector('.list-item-name').textContent;
			if (target.classList.contains('list-item-name')) {
				downloadAttachment(parent.dataset.id);
			}
			else if (target.classList.contains('btn_delete')) {
				deleteItem({
					action: 'delete',
					item: 'attachments',
					itemName: itemName,
					id: parent.dataset.id,
					callback: () => {
						getAttachmentsList(parent.dataset.uploaded_to);
					}
				});
			}
		}
	});

	pagesTitle.addEventListener('click', (e) => {
		e.preventDefault();
		const target = e.target;
		if (!target.getAttribute('contenteditable')) {
			target.setAttribute('contenteditable', true);
			target.innerText = b64DecodeUnicode(target.dataset.name);
			target.focus();
		}
	});

	pagesTitle.addEventListener('keyup', (e) => {
		const target = e.target;
		if (e.code === 'Escape') {
			target.blur();
		} else if (e.code === 'Enter') {
			saveBook(target.innerText, target.dataset.id);
		}
	});

	pagesTitle.addEventListener('blur', (e) => {
		const target = e.target;
		target.removeAttribute('contenteditable');
		target.innerText = b64DecodeUnicode(target.dataset.full_name);
	});

	const showItemList = (data, itemsList, attr = ['id']) => {
		itemsList.textContent = '';
		let datasetStr = '';
		if (data && data.success && data.success.answer && data.success.answer.data) {
			data.success.answer.data.forEach(item => {
				datasetStr = '';
				attr.forEach(elem => {
					if (item[elem]) {
						datasetStr += ` data-${elem}="${item[elem]}"`;
					}
				});
				itemsList.insertAdjacentHTML('beforeEnd', `
					<li ${datasetStr}>
						<div class="list-item">
							<span class="list-item-name">${item.name}</span>
							<button class="btn_delete">-</button>
						</div>
					</li>
				`);
			});
		} else {
			tinymce.activeEditor.notificationManager.open({
				text: 'An error occurred. You may not have permission to access the section',
				type: 'error'
			});
		}
	};

	const showPageContent = (data) => {
		if (data && data.success && data.success.answer) {
			try {
				const pageData = data.success.answer;
				tinymce.activeEditor.setContent(pageData.html);
				pageNameEdit.innerText = pageData.name;
				page_id = pageData.id;
				getPageList(pageData.book_id);
				getAttachmentsList(pageData.id);
			} catch (e) {
				tinymce.activeEditor.notificationManager.open({
					text: 'An error occurred. This page may have been removed',
					type: 'error'
				});
			}
		}
	};

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
	};

	async function sendPostFile(url, body) {
		try {
			const response = await fetch(url, {
				method: 'POST',
				body: body,
			});
			const data = await response.json();
			return data;
		} catch (e) {
			console.error(e);
		}
		return null;
	};

	const toggleSignIn = (mode) => {
		clearInputsForms();
		errorMsg.remove();
		$('#login-dialog').modal(mode);
	};

	const iniInterface = (useSignIn = false) => {
		currentUser = '';
		clearEditableFields();
		clearAllSection('data-showned');
		hideSuperUserElems();
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
		location.hash = '';
		localStorage.removeItem('showMosaicEditItems');
		sendRequest('POST', requestURL, body).then(() => {
			location.reload();
		});
	};

	const toggleSection = (showSection, addParams = {}) => {
		// let currentSection = location.hash.substring(1);
		if (showSection == '') return;
			// || currentSection == showSection) 

		document.title = startDocumentTitle;
		location.hash = showSection;
		let idx = 0;
		showSection = showSection.replace(/\s+/g, '');
		if (showSection !== 'documentation') {
			// section is document.querySelectorAll('.section');
			section.forEach((item, i) => {
				item.style.display = "none";
				if (showSection && item.classList.contains(showSection)) {
					idx = i;
				}
			});
			if (showSection === 'template' || 
				showSection === 'templateDIP' ||
				showSection === 'ctemplate' ||
				showSection === 'mop' || 
				showSection === 'dip' || 
				showSection === 'cmop' ||
				showSection === 'templatecDIP' ||
				showSection === 'cdip' || 
				showSection === 'cSDEPingtest' ||
				showSection === 'cSDEBundle'
				) {
				section[idx].append(renderMopDiv);
			}
			section[idx].style.display = "block";
		}

		clearOldData(showSection);

		cTemplate = 0;

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
			case 'services':
				document.title = 'Services';
				getMosaic();
				break;
			case 'documentation':
				// document.title = 'Documentation';
				// formSearch.reset();
				// formAddAttachment.reset();
				getBooksList();
				break;
			case 'template':
				document.title = 'Template MOP';
				templateDip = 0;
				displayMOPElements(true);
				iniOGPA();
				break;
			case 'templateDIP':
				document.title = 'Template DIP';
				templateDip = 1;
				displayMOPElements(true);
				iniOGPA();
				break;
			case 'ctemplate':
				document.title = 'cTemplate';
				templateDip = 0;
				cTemplate = 1;
				displayMOPElements(true);
				iniOGPA();
				break;
			case 'templatecDIP':
				document.title = 'Template cDIP';
				templateDip = 1;
				cTemplate = 2;
				displayMOPElements(true);
				iniOGPA();
				break;
			case 'mop':
				document.title = 'MOP';
				docTitle.value = 'Method of Procedure (MOP)';
				templateDip = 0;
				gCounterMode = "mopCounter";
				displayMOPElements(false);
				iniOGPA();
				break;
			case 'cmop':
				document.title = 'cMOP';
				docTitle.value = 'Method of Procedure (MOP)';
				templateDip = 0;
				cTemplate = 1;
				gCounterMode = "mopCounter";
				displayMOPElements(false);
				iniOGPA();
				break;
			case 'cdip':
				document.title = 'cDIP';
				docTitle.value = 'Design Implementation Procedure (DIP)';
				templateDip = 0;
				cTemplate = 2;
				gCounterMode = "mopCounter";
				displayMOPElements(false);
				iniOGPA();
				break;
			case 'dip':
				document.title = 'DIP';
				docTitle.value = 'Design Implementation Procedure (DIP)';
				templateDip = 1;
				gCounterMode = "dipCounter";
				displayMOPElements(false);
				if (addParams['element']) {
					if (addParams['element'] === 'Capacity') {
						document.title = addParams['visibleName'];
					} else if (addParams['visibleName'] === 'eFCR') {
						document.title = addParams['visibleName'];
						iniOGPA(addParams);
					} else {
						document.title = addParams['visibleName'];
						iniOGPA(addParams);
					}
				} else {
					iniOGPA();
				}
				break;
			case 'cSDEPingtest':
			case 'cSDEBundle':
				if (showSection == 'cSDEPingtest') {
					bundleLink.classList.remove('hidden');
				} else {
					bundleLink.classList.add('hidden');
				}
				document.title = documentProperties[showSection].title;
				cSDEType.value = documentProperties[showSection].csde_type;
				cSDEType.closest('fieldset').querySelector('legend').textContent = documentProperties[showSection].title;
				if (addParams.reload != false) {
					displayMOPElements(false);
					iniOGPA(addParams);
				}
				break;
			case 'inventory':
				document.title = 'Inventory';
				iniInventory();
			case 'projects':
				document.title = 'Projects';
				iniProjects();
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
			// if (target.dataset['section'] === 'documentation' && target.dataset.href.length > 10) {
			// 	window.open(target.dataset.href, '_blank');
			// }
			if (target.dataset.section === 'logout') {
				logout();
			} else if (target.dataset.section === 'login') {
				toggleSignIn('show');
			} else {
				if (location.hash.substring(1) == target.dataset.section) return;

				selectMenuItem(target.parentNode, target.dataset.section, (!!target.dataset.subsection) ? target.dataset.subsection : false);
				toggleSection(target.dataset.section, 
				{'element': (target.dataset.element === undefined) ? false : target.dataset.element, 
				'activity': (target.dataset.activity === undefined) ? false : target.dataset.activity,
				'visibleName': (target.dataset.visibleName === undefined) ? false : target.dataset.visibleName});
			}
		}
	});

	bundleLink.addEventListener('click', (e) => {
		e.preventDefault();
		const target = e.target;
		const licSDEBundle = document.querySelector('[data-section="cSDEBundle"]');
		selectMenuItem(licSDEBundle.parentNode, licSDEBundle.dataset.section, (!!licSDEBundle.dataset.subsection) ? licSDEBundle.dataset.subsection : false);
		toggleSection(licSDEBundle.dataset.section, 
		{'element': (licSDEBundle.dataset.element === undefined) ? false : licSDEBundle.dataset.element, 
		'activity': (licSDEBundle.dataset.activity === undefined) ? false : licSDEBundle.dataset.activity,
		'visibleName': (licSDEBundle.dataset.visibleName === undefined) ? false : licSDEBundle.dataset.visibleName,
		'reload': false,
		});
		// target.classList.add('hidden');
	});

	const selectMenuItem = (menu, section, subsection = false) => {
		Array.from(menu.children).forEach(item => {
			item.style.backgroundColor = '';
		});
		try {
			let selector = `[data-section="${section}"]`;
			if (subsection) {
				selector = `[data-section="${section}"][data-subsection="${subsection}"]`;
			}
			menu.querySelector(selector).style.backgroundColor = 'rgba(0,0,0,0.1)';
		} catch (e) {
		};
	};

	const showInterface = (data) => {
		let loginAction = 'logout';
		const rights = {};
		let subMenuClass_ = '';
		if (data) {
			menu.textContent = '';
			if (!!data.success) {
				if (data.success.answer.user === 'defaultUser') {
					currentHash = '';
					loginAction = 'login'
				} else {
					currentUser = data.success.answer.user;
				}
				
				data.success.answer.rights.forEach(({pageName, sectionAttr, sectionName, accessType}) => {
					if (pageName !== 'Documentation' && sectionName !== 'main')
					{
						if (pageName.toUpperCase() === 'MOP' || pageName.toUpperCase() === 'DIP') {
							pageName = pageName.toUpperCase();
						} else if (pageName === 'Ctemplate') {
							pageName = 'cTemplate';
						} else if (pageName === 'Cmop') {
							pageName = 'cMOP';
						}
						if (accessType != '') {	
							rights[sectionName] = accessType;
							let add_attrs = '';
							if (!!sectionChildren[sectionName] && Array.isArray(sectionChildren[sectionName])) {
								let temp_arr = sectionChildren[sectionName];
								add_attrs = `data-element="${temp_arr[0]}" data-activity="${temp_arr[1]}" data-csde_type="${temp_arr[2]}"`;
							}
							menu.insertAdjacentHTML('beforeend', `
							<li data-section="${sectionAttr}" data-access="${accessType}" ${add_attrs}>${pageName}</li>
							`);
							if (!!sectionChildren[sectionName] && typeof sectionChildren[sectionName] === 'object' && !Array.isArray(sectionChildren[sectionName])) {
								for (const [key, value] of Object.entries (sectionChildren[sectionName])) {
									menu.insertAdjacentHTML('beforeend', `<li data-section="${sectionAttr}" 
									data-visible-name="${key}" 
									data-access="${accessType}" data-element="${value[0]}" data-activity="${value[1]}"
									data-subsection="${value[2]}" class="${subMenuClass}">- ${key}</li>
									`);
								}
							}
							if (sectionName === 'excel')
							{
								if (accessType === 'user')
								{
									toggleNoAccessRights('period-select', 'user-none', 'none');
								} else {
									toggleNoAccessRights('period-select', 'user-none', '');
								}
							} else if (sectionName === 'services') {
								if (accessType === 'admin')
								{
									showMosaicEditItems = '1';
								} else {
									showMosaicEditItems = '0';
								}
								localStorage.setItem('showMosaicEditItems', showMosaicEditItems);
							}
						}
					}
				});
				wikiURL = data.success.answer.docsHref;
				wikiLDAPAuth = data.success.answer.doscLDAP;
				document.querySelector('#capacity-frame-link').textContent = data.success.answer.capacityHref;
				document.querySelector('#capacityFrame').src = data.success.answer.capacityHref;
			}
			menu.insertAdjacentHTML('beforeend', `
				<li data-section="${loginAction}">${capitalize(loginAction)}</li>
			`);
			$('#waitModal').modal('hide');
			let section = '';
			if (!!rights[currentHash]) {
				section = currentHash;
			}
			// if (currentHash === 'automator' && !!rights[currentHash]) {
			// 	section = 'automator';
			// } else if (currentHash === 'services' && !!rights[currentHash]) {
			// 	section = 'services';
			// } else if (currentHash === 'status' && !!rights[currentHash]) {
			// 	section = 'status';
			// }
			/* else if (currentHash === 'documentation' && !!rights[currentHash]) {
				section = 'documentation';
			} */
			selectMenuItem(menu, section);
			let paramSection = {};
			try {
				let sectionItem = document.querySelector(`li[data-section="${section}"]`);
				if (!!sectionItem.dataset.element && !!sectionItem.dataset.activity) {
					paramSection.element = sectionItem.dataset.element;
					paramSection.activity = sectionItem.dataset.activity;
				}
			} catch (e) {}
			toggleSection(section, paramSection);
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
		'getTagsByProject': function (data, container, addParameters) {
			fillSelect(data, container, addParameters);
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

	const modRights = (userName, newRights, token) => {
		const body = {
			method: 'setRights',
			params: {
				'userName': userName,
				'rights': newRights,
				'token': token,
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
		body.method = 'getGroupsList';
		sendRequest('POST', requestURLProject, body).then(showGroupsProject);
	}

	const getDataFromKanboard = (apiName, apiProps, container, addParameters = null) => {
		const body = {
			method: apiName,
		};
		if (addParameters) {
			try {
				for (const [key, value] of Object.entries(addParameters)) {
					body[key] = value;
				}
			} catch (e) {}
		}
		sendRequest('POST', requestURL, body).then((data) => {
			apiProps[apiName](data, container, addParameters);
		});
	};

	const fillSelect = (data, elemList, addParameters = null) => {
		if (!!data.success) {
			if (addParameters) {
				return fillObjSelect(data.success.answer, elemList, true);
			}
			elemList.innerHTML = '<option value="" selected disabled hidden>Choose...</option>';
			data.success.answer.forEach(function (item) {
				elemList.insertAdjacentHTML('beforeend', `
					<option value="${item}">${item}</option>
				`);
			});
		}
		elemList.value = '';
	};

	const fillObjSelect = (data, elemList, keyAsAttr = false) => {
		elemList.innerHTML = '<option value="" selected disabled hidden>Choose...</option>';
		try {
			data.forEach((item) => {
				for (const [key, value] of Object.entries(item)) {
					if (keyAsAttr) {
						elemList.insertAdjacentHTML('beforeend', `
						<option value="${value}" data-project="${key}">${value}</option>
					`);
					} else {
						elemList.insertAdjacentHTML('beforeend', `
						<option value="${key}">${value}</option>
					`);
					}
				}
			});
		}
		catch (e) {}
		elemList.value = '';
	};

	const editExcelTask = (e) => {
		e.preventDefault();
		const target = e.target;
		if (target.classList.contains('icon-edit')) {
			const taskTicket = target.closest('.task-ticket-excel');
			if (!!taskTicket) {
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
		e.preventDefault();
		const target = e.target;
		if (target.classList.contains('icon-edit')) {
			if (previousElem === target) {
				return false;
			}
			formNewTaskStatus.reset();
			previousElem = target;
			ticketCreatorStatus.value = currentUser;

			const taskID = setFieldsEditForm(target, '.task-ticket-status', taskStatus_id);

			inputGroupRequest.dispatchEvent(selectCnange);
			toggleToUpdateMode(btnUpdateTaskStatus, btnAddTaskStatus, attachmentsAreaStatus);
			// creatorApply.classList.remove('d-none');
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
				// ticketCreatorStatus.value = '';
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
					// ticketCreatorStatus.value = taskDescription.substring(positionCreator + textCreatorHeader.length, endPositionCreator).trim();	
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
		if (!!rowTask) {
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
			// delete prevValues['creatorStatus'];
			// document.querySelector('#prevValues').value = b64EncodeUnicode(JSON.stringify(prevValues));
			ticketCreatorStatus.dataset['old_value'] = ticketCreatorStatus.value;
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
		getDataFromKanboard('getTagsByProject', apiCallbackProps, ticketProjectNameStatus, {'splitProjects': '1'});
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

	const getMosaic = () => {
		initMosaicElems();
		mosaicTable.ajax.reload();
	};

	// const clearDevicesDataTemp = () => {
	// 	const body = {
	// 		env: 'services',
	// 		call: 'clearDevicesDataTemp',
	// 	};
	// 	sendRequest('POST', requestURL, body).then(getMosaic);
	// };

	const updateDevicesData = (args) => {
		const body = {
			env: 'services',
			call: 'updateDevicesData',
		};
		sendRequest('POST', requestURL, Object.assign(body, args)).then((data) => {
			if (data && data.success && data.success.answer) {
				showMosaic();
			} else {
				location.hash = '';
			}
		});
	};

	const updateInventoryData = (args) => {
		const body = {
			env: 'services',
			call: 'updateInventoryData',
		};
		sendRequest('POST', requestURL, Object.assign(body, args)).then((data) => {
			if (data && data.success && data.success.answer) {
				iniInventory();
			} else {
				location.hash = '';
			}
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
		if (data && data.success) {
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
		const projects = data.success.answer.pop();
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
		refreshBoardTable();
	};

	const showStatisticsTable = (data) => {
		dataTableStatistics.clear().draw();
		if (!!data.success) {
			const projects = data.success.answer.pop();
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
			const projects = data.success.answer.pop();
			fillObjSelect([projects], inputGroupRequest);
			inputGroupRequest.dispatchEvent(selectCnange);
			// data.success.answer.sort(byField('date_creation'));
			data.success.answer.sort(byField('kanboard_project_id', true));
			data.success.answer.forEach(function ({ id, title, assignee_name, status, date_creation, date_started, reference, description, project_name, fields, kanboard_project_name, kanboard_project_id }) {
				const submitted_name = getField(fields, 'creator', '');
				const originTaskID = getField(fields, 'origintask', id);
				tableStatus.insertAdjacentHTML('beforeend', `
				 	<tr class="task-ticket-status" data-task_id="${id}" data-origin_id="${originTaskID}">
						<td>${id}</td>
						<td data-item_value="${title}" data-item_id="titleStatus">${title}</td>
						<td data-item_value="${submitted_name}" data-item_id="creatorStatus">${submitted_name}</td>
						<td data-item_value="${getOTL(fields)}" data-item_id="OTLStatus">${assignee_name}</td>
						<td data-item_value="${kanboard_project_id}" data-item_id="inputGroupRequest">${kanboard_project_name}</td>
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

	const showUpdatedCreator = (data) => {
		if (!!data.success) {
			try {
				const selector = `.task-ticket-status[data-task_id="${data.success.answer.id}"]`;
				const tr = document.querySelector(selector);
				const creatorStatus = tr.querySelector('[data-item_id="creatorStatus"]');
				creatorStatus.innerText = data.success.answer.creator;
				creatorStatus.dataset['item_value'] = data.success.answer.creator;
			} catch (e) {
				ticketCreatorStatus.value = ticketCreatorStatus.dataset['old_value'];
			}
		}
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

	const showMosaic = () => {
		mosaicTable.ajax.reload();
	};

	const showComments = (deviceID, data) => {
		if (data && data.success) {
			commentsText.textContent = '';
			data.success.answer.forEach(item => {
				commentsText.insertAdjacentHTML('beforeend',
					`<p class="comment-text" id="comment_${item.id}"><span class="comment-date">${item.date}</span>${cr2br(item.comment)}</p>`
				);
			});
			btnCommentsModal.dataset.device_id = deviceID;
			$('#inventoryComments').modal({
				keyboard: true
		  });
		} else {
			console.log(data);
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

	addUserGroup.addEventListener('click', function()
	{
		const selectedUser = document.querySelector('input[name="select-users-all"]:checked');
		const body = {
			method: 'addUserToGroup',
			user_name: selectedUser.dataset.user_name,
			group_id: groupsList.options[groupsList.selectedIndex].dataset.group_id
		}
		sendRequest('POST', requestURLProject, body).then(showGroupsProject);
	});

	removeUserFromGroup.addEventListener('click', function()
	{
		const selectedUser = document.querySelector('input[name="select-users-group"]:checked');
		const body = {
			method: 'removeUserFromGroup',
			user_name: selectedUser.dataset.user_name,
			group_id: groupsList.options[groupsList.selectedIndex].dataset.group_id
		}
		sendRequest('POST', requestURLProject, body).then(showGroupsProject);
	});

	btnGroupAppend.addEventListener('click', (e) => {
		const idx = groupsListProjects.options[groupsListProjects.selectedIndex].dataset.group_id;
		projectGroups.insertAdjacentHTML('beforeend', `
			<input type="radio" name="select-groups-project" data-user_name="${groupsListProjects.value}" id="opt-gr-pr${idx}">
			<label for="opt-gr-pr${idx}">${groupsListProjects.value}</label>
		`);
	});

	btnGroupRemove.addEventListener('click', (e) => {
		const selectedGroup = document.querySelector('input[name="select-groups-project"]:checked');
		console.log(`label[for="opt-gr-pr${selectedGroup.id}"]`);
		document.querySelector(`label[for="${selectedGroup.id}"]`).remove();
		selectedGroup.remove();
	});

	formNewProject.addEventListener('submit', (e) => {
		e.preventDefault();
		const body = {
			method: 'addProject',
			value: projectName.value.trim(),
			number: projectNumber.value.trim(),
			text_field: projectText.textContent.trim()
		}
		sendRequest('POST', requestURLProject, body).then(showProjectInfo);
	});

	formNewProject.addEventListener('reset', (e) => {
		projectText.textContent = '';
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

	tableInventory.addEventListener('click', function (e) {
		e.preventDefault();
		const target = e.target;
		const expandInventory = target.closest('.js-expandInventory');
		const deleteTag = target.closest('.delete-tag');
		const saveTags = target.closest('.js-saveTags');
		const newChassis = target.closest('.js-newChassis');
		const undoNewChassis = target.closest('.js-undoNewChassis');
		
		if (expandInventory) {
			const parent_tr = expandInventory.closest('tr');
			const parent_a = expandInventory.closest('a');
			const chassisID = parent_tr.dataset.id;
			if (expandInventory.dataset.collapse != null) {
				const inventoryChilds = expandInventory.closest('table').querySelector('#inventoryChilds');
				try {
					inventoryChilds.remove();
				} catch (e) {
				}
				// tableParts.classList.add('hidden');
				tableTags.classList.add('hidden');
				// expandInventory.dataset['expanded'] = '0';
				expandInventory.closest('a').querySelector('[data-expand]').classList.remove('hidden');
				expandInventory.classList.add('hidden');
			} else if (expandInventory.dataset.expand != null){
				const rowIndex = parent_tr.rowIndex;
				const newRow = expandInventory.closest('table').insertRow(rowIndex + 1);
				newRow.id = 'inventoryChilds';
				newRow.dataset.id = chassisID;
				const newCell = newRow.insertCell();
				newCell.colSpan = parent_tr.cells.length;
				newCell.append(tableParts, tableTags);
				// tableParts.classList.remove('hidden');
				tableTags.classList.remove('hidden');
				expandInventory.closest('a').querySelector('[data-collapse]').classList.remove('hidden');
				expandInventory.classList.add('hidden');
				getChassisTags(chassisID);
			} if (expandInventory.dataset.edit != null) {
				const selfCell = expandInventory.closest('td');
				expandInventory.closest('tr').querySelectorAll('td').forEach((item) => {
					if (item !== selfCell) {
						item.setAttribute('contenteditable', true);
					}
				});
				expandInventory.closest('a').querySelector('[data-undo]').classList.remove('hidden');
				expandInventory.closest('a').querySelector('[data-done]').classList.remove('hidden');
				expandInventory.classList.add('hidden');
			} if (expandInventory.dataset.undo != null) {
				if (+chassisID) {
					expandInventory.closest('tr').querySelectorAll('td').forEach((item) => {
						const selfCell = expandInventory.closest('td');
						if (item !== selfCell) {
							item.removeAttribute('contenteditable');
							item.textContent = item.dataset.value;
						}
					});
					expandInventory.closest('a').querySelector('[data-edit]').classList.remove('hidden');
					expandInventory.closest('a').querySelector('[data-done]').classList.add('hidden');
					expandInventory.classList.add('hidden');
				} else {
					tInventory.querySelector('[data-id="0"]').remove();
					this.querySelector('.js-newChassis').classList.remove('hidden');
					this.querySelector('.js-undoNewChassis').classList.add('hidden');
				}
			} if (expandInventory.dataset.done != null) {
				const selfCell = expandInventory.closest('td');
				const newData = {};
				expandInventory.closest('tr').querySelectorAll('td').forEach((item) => {
					if (item !== selfCell) {
						newData[item.dataset.field] = item.textContent.trim();
						item.removeAttribute('contenteditable');
					}
				});
				setChassisData(chassisID, newData);
				expandInventory.closest('a').querySelector('[data-edit]').classList.remove('hidden');
				expandInventory.closest('a').querySelector('[data-undo]').classList.add('hidden');
				expandInventory.classList.add('hidden');
				this.querySelector('.js-newChassis').classList.remove('hidden');
				this.querySelector('.js-undoNewChassis').classList.add('hidden');
			}
		} else if (deleteTag) {
			inventoryTagsSet.delete(deleteTag.closest('.inventory-tag').dataset.tag);
			fillInventoryTags(inventoryTagsSet);
		} else if (saveTags) {
			setChassisTags(saveTags.closest('#inventoryChilds').dataset.id);
		} else if (newChassis) {
			tInventory.insertAdjacentHTML('afterbegin', `
				<tr data-id="0">
					<td data-field="chassis_name" data-value="" contenteditable>&nbsp;</td>
					<td data-field="vendor" data-value="" contenteditable>&nbsp;</td>
					<td data-field="model" data-value="" contenteditable>&nbsp;</td>
					<td data-field="software" data-value="" contenteditable>&nbsp;</td>
					<td data-field="serial" data-value="" contenteditable>&nbsp;</td>
					<td data-field="year_service" data-value="" contenteditable>&nbsp;</td>
					<td data-field="comment" data-value="" contenteditable>&nbsp;</td>
					<td>
						<a href="#">
							<img class="icon-edit icon-edit-sm js-expandInventory hidden" data-edit src="img/edit.svg" title="Edit">
							<img class="icon-edit icon-edit-sm js-expandInventory" data-undo src="img/undo.svg" title="Undo">
							<img class="icon-edit icon-edit-sm js-expandInventory" data-done src="img/done.svg" title="Done">
							<img class="icon-edit icon-edit-sm js-expandInventory hidden" data-expand src="img/expand_content.svg" title="Detail">
							<img class="icon-edit icon-edit-sm hidden js-expandInventory" data-collapse src="img/collapse_content.svg">
						</a>
					</td>
				</tr>
			`);
			newChassis.closest('a').querySelector('.js-undoNewChassis').classList.remove('hidden');
			target.classList.add('hidden');
		} else if (undoNewChassis) {
			tInventory.querySelector('[data-id="0"]').remove();
			undoNewChassis.closest('a').querySelector('.js-newChassis').classList.remove('hidden');
			target.classList.add('hidden');
		}
	});

	inventoryTags.addEventListener("keydown", (event) => {
		const newTag = event.target.closest('.new-tag');
		if (newTag) {
			if (event.code === 'Enter') {
				event.preventDefault();
				inventoryTagsSet.add(newTag.innerText);
				fillInventoryTags(inventoryTagsSet);
			}
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

	const hideEditItems = function () {
		if (!modifyContainer.classList.contains('d-none')){
			modifyContainer.classList.add('d-none');
		}
	};

	const setAvailFormElements = (element, disableMode = false) => 
	{
		if (disableMode == false) {
			element.classList.remove('hidden');
			element.disabled = false;
		} else if (disableMode == true) {
			element.classList.add('hidden');
			element.disabled = true;
		}
	};

	const initMosaicElems = function () {
		if (showMosaicEditItems == '1') {
			mosaicForm.reset();
		}
		else {
			hideEditItems();
		}
	};

	const deviceActionMosaic = function(e) {
		e.preventDefault();
		const target = e.target;
		const mosaicRow = target.closest(`.${mosaicTableSelector}`);
		const parent_a = target.closest('a');
		let action = null;
		const tr_id = mosaicTable.row(target.closest('tr')).id();
		const device_id = tr_id.slice(tr_id.indexOf('_') + 1);

		if (target.classList.contains('icon-edit'))
		{
			action = 'modify';
		} else if (target.classList.contains('icon-delete')) {
			action = 'delete';
		} else if (target.dataset.undo != null) {
			parent_a.querySelector('[data-edit]').classList.remove('hidden');
			parent_a.querySelector('[data-done]').classList.add('hidden');
			parent_a.querySelector('[data-lock]').classList.add('hidden');
			parent_a.querySelector('[data-open]').classList.add('hidden');
			target.classList.add('hidden');
			mosaicRow.querySelectorAll('span.editable').forEach(resetEdit);
		} else if (target.dataset.done != null) {
			const args = {};
			target.closest('tr').querySelectorAll('.editable').forEach(item => {
				args[item.dataset.name] = item.textContent;
				if (item.dataset.name == 'platform') {
					args['oldPlatform'] = item.dataset.value;
				}
			});
			args['id'] = device_id;		
			args['locked'] = parent_a.dataset.locked;
			updateDevicesData(args);
			parent_a.querySelector('[data-edit]').classList.remove('hidden');
			parent_a.querySelector('[data-done]').classList.add('hidden');
			parent_a.querySelector('[data-lock]').classList.add('hidden');
			parent_a.querySelector('[data-open]').classList.add('hidden');
			target.classList.add('hidden');
			mosaicRow.querySelectorAll('span.editable').forEach(resetEdit);
		} else if (target.dataset.lock != null) {
			parent_a.dataset.locked = '0';
			mosaicRow.dataset.locked = '0';
			parent_a.querySelector('[data-open]').classList.remove('hidden');
			target.classList.add('hidden');
		} else if (target.dataset.open != null) {
			parent_a.dataset.locked = '1';
			mosaicRow.dataset.locked = '1';
			parent_a.querySelector('[data-lock]').classList.remove('hidden');
			target.classList.add('hidden');
		}
		switch (action) {
			case 'modify':
				modifyDeviceSettings({
					device_id: device_id,
					row_id: mosaicRow.id
				});
				break;
			case 'delete':
				reqDeleteDevice({
					name: mosaicRow.querySelector('.name-text').innerText,
					id: device_id
				});
				break;
		}
	};

	const deviceActionNodes = function(e, params) {
		e.preventDefault();
		const target = e.target;
		const nodesRow = target.closest('tr');
		const parent_a = target.closest('a');
		let action = null;
		const device_id = nodesRow.dataset.node_id;

		if (target.classList.contains('icon-edit'))
		{
			nodesRow.querySelectorAll('span.editable').forEach(setEdit);
			switchTableAction({
				'lockMode': 1, 
				'dataLocked': [parent_a, nodesRow],
				'shownedElems': [
					parent_a.querySelector('[data-undo]'),
					parent_a.querySelector('[data-done]'),
					parent_a.querySelector('[data-lock]'),
				],
				'hiddenElems': [
					parent_a.querySelector('[data-edit]'),
					parent_a.querySelector('[data-open]'),
				],
			});
		} else if (target.classList.contains('icon-delete')) {
			action = 'delete';
		} else if (target.dataset.undo != null) {
			switchTableAction({
				'lockMode': null, 
				'dataLocked': [],
				'shownedElems': [parent_a.querySelector('[data-edit]')],
				'hiddenElems': [
					parent_a.querySelector('[data-done]'),
					parent_a.querySelector('[data-lock]'),
					parent_a.querySelector('[data-open]'),
					target,
				],
			});
			nodesRow.querySelectorAll('span.editable').forEach(resetEdit);
		} else if (target.dataset.done != null) {
			const args = {};
			nodesRow.querySelectorAll('.editable').forEach(item => {
				args[item.dataset.name] = item.textContent;
				if (item.dataset.name == 'platform') {
					args['oldPlatform'] = item.dataset.value;
				}
			});
			args['id'] = device_id;		
			args['locked'] = parent_a.dataset.locked;
			updateInventoryData(args);
			switchTableAction({
				'lockMode': null, 
				'dataLocked': [],
				'shownedElems': [parent_a.querySelector('[data-edit]')],
				'hiddenElems': [
					parent_a.querySelector('[data-undo]'),
					parent_a.querySelector('[data-lock]'),
					parent_a.querySelector('[data-open]'),
					target,
				],
			});
			nodesRow.querySelectorAll('span.editable').forEach(resetEdit);
		} else if (target.dataset.lock != null) {
			switchTableAction({
				'lockMode': 0, 
				'dataLocked': [parent_a, nodesRow],
				'shownedElems': [parent_a.querySelector('[data-open]')],
				'hiddenElems': [target],
			});
		} else if (target.dataset.open != null) {
			switchTableAction({
				'lockMode': 1, 
				'dataLocked': [parent_a, nodesRow],
				'shownedElems': [parent_a.querySelector('[data-lock]')],
				'hiddenElems': [target],
			});
		} else if (target.closest('.comments-brief') || (target.querySelector('.comments-brief'))) {
			getComments(device_id);
		}
		switch (action) {
			case 'delete':
				reqDeleteDevice({
					name: nodesRow.querySelector('.name-text').innerText,
					table: nodesRow.dataset.table,
					id: device_id
				});
				break;
		}
	};

	const deviceKeyDown = function(e) {
		const target = e.target;
		const newTag = target.closest('.new-tag');
		if (newTag) {
			if (e.code === 'Enter') {
				e.preventDefault();
				const mosaicRow = target.closest(`.${mosaicTableSelector}`);
				const platformName = mosaicRow.querySelector('[data-name="platform"]').dataset.value;
				const groupName = mosaicRow.querySelector('[data-name="group"]').dataset.value;
				const ownerName = mosaicRow.querySelector('[data-name="owner"]').dataset.value;

				if (target.dataset.name === 'owner' && target.innerText.trim() != target.dataset.value)
				{
					titleDialogModal.innerText = 'Change Owner';
					questionDialogModal.innerText = `Do you really want to change owner from : ${target.dataset.value} to ${target.innerText.trim()}?`;
					btnDialogModal.setAttribute('modal-command', 'changeOwner');
					btnDialogModal.dataset['id'] = mosaicRow.dataset.node_id;
					btnDialogModal.dataset['locked'] = mosaicRow.dataset.locked;
					btnDialogModal.dataset['oldOwner'] = target.dataset.value;
					btnDialogModal.dataset['newOwner'] = target.innerText.trim();
					btnDialogModal.dataset['group'] = groupName;

					$('#dialogModal').modal({
						keyboard: true
					});
				} else if (target.dataset.name === 'group' && target.innerText.trim() != target.dataset.value) {
					titleDialogModal.innerText = 'Change Group';
					questionDialogModal.innerText = `Do you really want to change group from : ${target.dataset.value} to ${target.innerText.trim()}?`;
					btnDialogModal.setAttribute('modal-command', 'changeGroup');
					btnDialogModal.dataset['id'] = mosaicRow.dataset.node_id;
					btnDialogModal.dataset['locked'] = mosaicRow.dataset.locked;
					btnDialogModal.dataset['oldGroup'] = target.dataset.value;
					btnDialogModal.dataset['group'] = target.innerText.trim();;
					btnDialogModal.dataset['platform'] = platformName;

					$('#dialogModal').modal({
						keyboard: true
					});
				}
			}
		}
	};

	// const deviceActionEFCR = function(e, params) {
	// 	e.preventDefault();
	// 	const target = e.target;
	// 	const nodesRow = target.closest('tr');
	// 	const parent_t = target.closest('table');
	// 	const parent_a = target.closest('a');
	// 	let action = null;

	// 	if (target.dataset.add !== undefined)
	// 	{
	// 		dataTableEFCR.row.add({}).draw();
	// 	} else if (target.dataset.addCopy !== undefined) {
	// 		dataTableEFCR.row.add(dataTableEFCR.row(nodesRow).data()).draw();
	// 	} else if (target.dataset.delete !== undefined) {
	// 		dataTableEFCR.row(nodesRow).remove().draw();
	// 	}
	// };

	// const efcrKeyDown = function(e) {
	// 	const target = e.target;
	// 	if (e.code === 'Enter') {
	// 		e.preventDefault();
	// 		const nodeCell = target.closest('td');
	// 		if (nodeCell) {
	// 			dataTableEFCR.cell(nodeCell).data(nodeCell.textContent);
	// 		}
	// 	}
	// }

	// const efcrFocusOut = function(e) {
	// 	const target = e.target;
	// 		const nodeCell = target.closest('td');
	// 		if (nodeCell) {
	// 			dataTableEFCR.cell(nodeCell).data(nodeCell.textContent);
	// 		}
	// }

	const resetEdit = (item) => {
		item.removeAttribute('contenteditable');
		item.classList.remove('new-tag');
		item.textContent = item.dataset.value;
	};

	const setEdit = (item) => {
		item.setAttribute('contenteditable', true);
		item.classList.add('new-tag');
	};

	const switchTableAction = (params) => {
		if (params.lockMode != null) {
			params.dataLocked.forEach(item => item.dataset.locked = params.lockMode);
		}
		params.shownedElems.forEach(item => item.classList.remove('hidden'));
		params.hiddenElems.forEach(item => item.classList.add('hidden'));
	};

	const reqDeleteDevice = (deviceParams) =>{
		titleDialogModal.innerText = 'Delete node';
		questionDialogModal.innerText = `Do you really want to delete the node: ${deviceParams['name']}?`;
		btnDialogModal.setAttribute('modal-command', 'deleteDevice');
		btnDialogModal.dataset['id'] = deviceParams['id'];
		btnDialogModal.dataset['table'] = deviceParams['table'];
		$('#dialogModal').modal({
			keyboard: true
		});
	};

	const servicesImportLog = (data) =>{
		titleDialogModal.innerText = 'Import log';
		questionDialogModal.classList.add('question-dialog-import');
		questionDialogModal.innerText = `Added: ${data['new_devices']}\nModified: ${data['mod_devices']}\nSkipped: ${data['skip_devices']}\n`;
		questionDialogModal.insertAdjacentHTML('beforeend', data['rows_log'].join('<br>'));
		const hideDialogButton = btnDialogModal.closest('.modal-footer').querySelector('[data-dismiss="modal"]');
		hideDialogButton.classList.add('hidden');
		btnDialogModal.setAttribute('modal-command', 'closeImportDialog');
		$('#dialogModal').modal({
			keyboard: true
		});
	};

	const modifyDeviceSettings = ({device_id, row_id}) => {
		const rowDevice = document.querySelector(`#${row_id}`);
		rowDevice.querySelectorAll('span.editable').forEach(setEdit);
		rowDevice.querySelector('[data-edit]').classList.add('hidden');
		rowDevice.querySelector('[data-undo]').classList.remove('hidden');
		rowDevice.querySelector('[data-done]').classList.remove('hidden');
		rowDevice.querySelector('[data-lock]').classList.remove('hidden');
		rowDevice.querySelector('[data-open]').classList.add('hidden');
	};

	const confirmDialog = (e) => {
		const target = e.target;
		const modalCommand = target.getAttribute('modal-command');
		switch(modalCommand)
		{
			case 'deleteDevice':
				if (target.dataset.table == 'inventory') {
					deleteInventory(target.dataset['id']);
				} else {
					deleteDevice(target.dataset['id']);
				}
				break;
			case 'importOGPA':
				importOGPA({
					'element': target.dataset.element,
					'acivity': target.dataset.activity
				});
				break;
			case 'changeOwner':
				changeOwner({
					'id': target.dataset.id,
					'locked': target.dataset.locked,
					'oldOwner': target.dataset.oldOwner, 
					'owner': target.dataset.newOwner,
					'group': target.dataset.group,
				});
				break;
			case 'changeGroup':
				changeGroup({
					'id': target.dataset.id,
					'locked': target.dataset.locked,
					'oldGroup': target.dataset.oldGroup, 
					'group': target.dataset.group,
					'platform': target.dataset.platform,
				});
				break;
			case 'closeImportDialog':
				const hideDialogButton = target.closest('.modal-footer').querySelector('[data-dismiss="modal"]');
				hideDialogButton.classList.remove('hidden');
				questionDialogModal.classList.remove('question-dialog-import');
				break;
		}
	};

	const deleteDevice = (deviceID) => {
		const body = {
			env: 'services',
			call: 'doDeleteDevice',
			mode: 'fast',
			id: deviceID
		};
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer) {
				showMosaic(data.success.answer);
			}
		});
	};

	const deleteInventory = (deviceID) => {
		const body = {
			env: 'services',
			call: 'doDeleteInventory',
			id: deviceID
		};
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer) {
				iniInventory();
			}
		});
	};

	const getComments = deviceID => {
		const body = {
			env: 'services',
			call: 'doGetComments',
			id: deviceID
		};
		sendRequest('POST', requestURL, body).then((data) => {
			showComments(deviceID, data);
		});
	};

	const changeOwner = ({id, locked, oldOwner, owner, group}) => {
		const body = {
			'env': 'services',
			'call': 'doChangeOwner',
			'id': id,
			'locked': locked,
			'oldOwner': oldOwner,
			'owner': owner,
			'group': group,
		};
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer) {
				showMosaic(data.success.answer);
			}
		});
	};

	const changeGroup = ({id, locked, oldGroup, group, platform}) => {
		const body = {
			'env': 'services',
			'call': 'doChangeGroup',
			'id': id,
			'locked': locked,
			'oldGroup': oldGroup,
			'group': group,
			'platform': platform,
		};
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer) {
				showMosaic(data.success.answer);
			}
		});
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

	$('#dialogModal').on('hidden.bs.modal', function (e) {
		const target = e.target;
		const hideDialogButton = target.querySelector('[data-dismiss="modal"]');
		hideDialogButton.classList.remove('hidden');
		questionDialogModal.classList.remove('question-dialog-import');
	});

	$('#inventoryComments').on('hidden.bs.modal', function (e) {
		iniInventory();
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
		if (data.success && data.success.answer) {
			clearInputsForms();
			usersList.textContent = '';
			ProjectUsersList.length = 0;
			data.success.answer.forEach((item) => {
				usersList.insertAdjacentHTML('beforeend', `
					${fillUserRights(item)}
				`);
			});
			allUsers.textContent = '';
			let idx = 1;
			ProjectUsersList.forEach((user) => {
				allUsers.insertAdjacentHTML('beforeend', `
					<input type="radio" name="select-users-all" data-user_name="${user}" id="project-user${idx}">
					<label for="project-user${idx}">${user}</label>
				`);
				idx++;
			});
		}
	}

	function showGroupsProject(data) {
		groupsUsersList.length = 0;
		if (data.success && data.success.answer) {
			groupsList.textContent = '';
			let firstGroupID = 0;

			data.success.answer.forEach((item) => {
				groupsList.insertAdjacentHTML('beforeend', `
					<OPTION value="${item.name}" data-group_id="${item.id}">${item.name}</OPTION>
					`);
				groupsUsersList.push({
					"group_id": item.id,
					"users": item.users
				});
				if (firstGroupID == 0) {
					firstGroupID = item.id;
				}
			});
			fillGroupUsers(firstGroupID);
		}
	}

	function showProjectsList(data) {
		projectsList.textContent = '';
		if (data.success && data.success.answer) {
			let idx = 1;
			data.success.answer.forEach((item) => {
				projectsList.insertAdjacentHTML('beforeend', `
					<input type="radio" name="select-projects" data-user_name="${item.name}" id="opt-project-${idx}" data-project_id="${item.id}">
					<label for="opt-project-${idx}">${item.name}</label>
				`);
				idx++;
			});
		}
	}

	function showProjectInfo(data) {
		console.log(data);
	}

	function showGroupsProjectSection(data) {
		formNewProject.reset();
		groupsListInProjects.length = 0;
		if (data.success && data.success.answer) {
			data.success.answer.forEach((item) => {
				groupsListInProjects.push({
					"id": item.id,
					"name": item.name
				});
			});
		}
		fillGroupsProject(groupsListProjects);
	}

	function fillGroupsProject(groupsListFilled) {
		groupsListFilled.textContent = '';
		groupsListInProjects.forEach((item) => {
			groupsListFilled.insertAdjacentHTML('beforeend', `
					<OPTION value="${item.name}" data-group_id="${item.id}">${item.name}</OPTION>
			`);
		});
	}

	function fillGroupUsers(group_id) {
		groupUsers.textContent = '';

		for (let group_obj of groupsUsersList) {
			if (group_obj.group_id == group_id) {
				if (group_obj.users) {
					let idx = 1;
					JSON.parse(group_obj.users).forEach((user) => {
						groupUsers.insertAdjacentHTML('beforeend', `
							<input type="radio" name="select-users-group" data-user_name="${user}" id="opt${idx}">
  							<label for="opt${idx}">${user}</label>
						`);
						idx++;
					});
				}
				break;
			}
		}
	}

	function fillUserRights(userRights) {
		let userName = '';
		let oneUserRights = defaultUserRights.slice();
		for (const [key, value] of Object.entries(userRights)) {
			if (key === 'user') {
				let idx = sections.indexOf(key);
				if (idx >= 0) {
					userName = value;
					oneUserRights[idx] = `<td data-${key}>${userName}</td>`;
				}
				ProjectUsersList.push(userName);
			} else if (key === 'rights') {
				value.forEach((right) => {
					let idx = sections.indexOf(right.sectionName);
					if (idx >= 0) {
						oneUserRights[idx] = `<td data-mode="${right.sectionName}" data-select="${right.accessType}">${right.accessType}</td>`;
					}
				});
				let idx = sections.indexOf('action');
				if (idx >= 0) {
					oneUserRights[idx] = `
					<td>
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

	loadExcelData.addEventListener('change', function(e) {
		mosaicFormLoadData.requestSubmit();
	});

	loadInventory.addEventListener('change', function(e) {
		inventoryFormLoadData.requestSubmit();
	});

	loadEFCR.addEventListener('change', function(e) {
		formEFCR.requestSubmit();
	});
	

	// btnClearData.addEventListener('click', function(e) {
	// 	clearDevicesDataTemp();
	// });

	tableUsers.addEventListener('click', actionForUsers);
	rightsForm.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		let newRights = [];
		let userName = '', 
			token = {
				token_id: '',
				token_secret: '',
			};
		for (let [key, value] of formData.entries()) {
			let valueClean = value.trim();
			if (key === 'userName') {
				userName = valueClean;
				continue;
			} else if (key === 'token_id' || key === 'token_secret') {
				if (valueClean.trim().length > 30) {
					token[key] = valueClean;
				}
				continue;
			}
			newRights.push({
				pageName: capitalize(key),
				sectionName: key,
				sectionAttr: key,
				accessType: valueClean,
			});
		}
		if (token.token_id === '' || token.token_secret === '') {
			token = {
				token_id: '',
				token_secret: '',
			};
		}
		if (userName != '') {
			modRights(userName, newRights, token);
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

	cacheForm.addEventListener('submit', function (e) {
		e.preventDefault();
		const span = this.querySelector('span');
		span.classList.add('d-none');
		const body = {
			method: 'installCacheTable',
		}
		sendRequest('POST', requestURL, body).then((data) => {
			if (data && data.success && data.success.answer) {
				span.classList.remove('d-none');
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
		ticketCreatorStatus.dataset['old_value'] = '';
		btnUpdateTaskStatus.classList.add('d-none');
		btnUpdateTaskStatus.disabled = true;
		btnAddTaskStatus.classList.remove('d-none');
		previousElem = null;
		selectTR('.task-ticket-status');
	});

	inputGroupRequest.addEventListener('change', function() {
		const slaveSelect = document.querySelector('#' + this.dataset['slave']);
		const projectID = this.value;
		if (slaveSelect) {
			const oldValue = slaveSelect.value;
			slaveSelect.value = '';
			slaveSelect.querySelectorAll('option').forEach((item) => {
				if (!item.disabled) {
					if (item.dataset['project'] == projectID) {
						if (oldValue == item.value) {
							slaveSelect.value = oldValue;
						}
						item.classList.remove('d-none');
					} else {
						item.classList.add('d-none');
					}
				}
			});
		}
	});

	formNewTaskStatus.addEventListener('submit', (e) => {
		e.preventDefault();

		const formData = new FormData(e.target);
		const arrData = {};
		let method = 'createTask';
		let callback = attachFileStatus;
		let updateCreator = false;

		if (taskStatus_id.value != 0 && ticketCreatorStatus.dataset['old_value'] !== ticketCreatorStatus.value) {
			updateCreator = true;
			method = 'updateCreator';
			callback = showUpdatedCreator;
		}

		for (let [key, value] of formData.entries()) {
			if (typeof value == 'object') continue;
			arrData[key] = value.trim();
		}
		arrData['description'] = ticketDescriptionStatus.innerText;
		if (taskStatus_id.value != 0 && !updateCreator) {
			arrData['version'] = 1;
		}

		const body = {
			method: method,
			params: arrData,
		};
		sendRequest('POST', requestURL, body).then(callback);
	});

	// creatorApply.addEventListener('click', (e) => {
	// 	e.preventDefault();
	// 	formNewTaskStatus.requestSubmit();
	// 	return true;
	// 	const target = e.target.closest('a');
	// 	const inputCreatorName = document.querySelector('#' + target.dataset['input_id']);
	// 	if (inputCreatorName) {
	// 		const body = {
	// 			method: 'updateCreator',
	// 			params: {
	// 				'creator': inputCreatorName.value.trim(),
	// 				'id': taskStatus_id.value,
	// 				'section': 'status'
	// 			}
	// 		};
	// 		sendRequest('POST', requestURL, body).then(showUpdatedCreator);
	// 	}
	// });

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
		
	});

	// init mosaic
	mosaicForm.addEventListener('reset', (e) => {
		const target = e.target;
		target.querySelector('#mosaicNodeComments').textContent = '';
		target.querySelector('[data-action="update"]').style.display = 'none';
		target.querySelector('[data-action="add"]').style.display = '';
	});

	mosaicForm.addEventListener('submit', (e) => {
		e.preventDefault();
		const target = e.target;
		const arrData = {};
		const formData = new FormData(e.target);
		const btnAction = document.activeElement;
		if (btnAction.type === 'submit') {
			for (let [key, value] of formData.entries()) {
				if (typeof value == 'object') continue;
				arrData[key] = value.trim();
			}
			const call = (btnAction.getAttribute('data-action') === 'add') ? 'doAddDevice' : 'doApplyDeviceSettings';
			arrData['env'] = 'services';
			arrData['call'] = call;
			arrData['comments'] = target.querySelector('#mosaicNodeComments').innerText.trim();

			sendRequest('POST', requestURL, arrData).then(getMosaic);
		}
	});

	mosaicFormLoadData.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		formData.append('env', 'services');
		formData.append('call', 'loadData');

		sendFile('POST', requestURL, formData).then((data) => {
			servicesImportLog(data.success.answer);
			showMosaic();
		});
	});

	inventoryFormLoadData.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		formData.append('env', 'services');
		formData.append('call', 'loadInventory');

		sendFile('POST', requestURL, formData).then((data) => {
			servicesImportLog(data.success.answer);
			iniInventory();
		});
	});

	const removeChildElementFromCollection = (collection, selector) => {
		collection.querySelectorAll(selector).forEach(row => {
			row.remove();
		});
	};

	const setSelectItem = (collection, selector, value) => {
		let selectCollection = collection.querySelector(selector);
		let selectedIndex = null;
		for (let option of selectCollection.options) {
			if (option.value.toLowerCase() == value.toLowerCase()) {
				selectedIndex = option.index;
				break;
			}
		}
		if (selectedIndex !== null) {
			selectCollection.selectedIndex = selectedIndex;
		} else {
			selectCollection.querySelector('.editable').value = value;
			selectCollection.querySelector('.editable').textContent = value;
			selectCollection.value = value;
			selectCollection.closest('div').querySelector('.editOption').value = value;
		}
	};
	
	formEFCR.addEventListener('submit', (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		formData.set('env', 'services');
		formData.set('call', 'loadEFCR');

		sendFile('POST', requestURL, formData).then((data) => {
			if (data.success && data.success.answer) {
				let count_rows = 1;
				const fieldset = document.querySelector(`[for="loadEFCR"]`).closest('fieldset');

				removeChildElementFromCollection(fieldset, '.multirows[data-clone="1"]');
				let row_values = fieldset.querySelector('.multirows');
				data.success.answer.forEach(item => {
					if (count_rows > 1) {
						row_values = btnAreaAppend(fieldset.querySelector('.multirows'));				
					}
					row_values.querySelector('[data-name="dipeFCRNumber"]').value = item.eFCRnumber;
					row_values.querySelector('[data-name="dipPolicyName"]').value = item.policyName;

					row_values.querySelector('[data-name="dipSourceSubnet"]').textContent = item.sourceSubnet;
					row_values.querySelector('[data-name="dipDestinationSubnet"]').textContent = item.destinationSubnet;
					row_values.querySelector('[data-name="dipPort"]').value = item.port;
					
					setSelectItem(row_values, '[data-name="dipSourceZone"]', item.sourceZone);
					setSelectItem(row_values, '[data-name="dipDestinationZone"]', item.destinationZone);
					setSelectItem(row_values, '[data-name="dipProtocol"]', item.protocol);
					setSelectItem(row_values, '[data-name="dipPHUBSites"]', item.PHUBSites);
					count_rows++;
				});
				// dataTableEFCR.clear().draw();
				// data.success.answer.forEach(item => {
				// 	dataTableEFCR.row.add({
				// 		'eFCRnumber': item.eFCRnumber,
				// 		'policyName': item.policyName,
				// 		'sourceZone': item.sourceZone,
				// 		'sourceSubnet': item.sourceSubnet,
				// 		'destinationZone': item.destinationZone,
				// 		'PHUBSites': item.PHUBSites,
				// 		'destinationSubnet': item.destinationSubnet,
				// 		'protocol': item.protocol,
				// 		'port': item.port,
				// 	});
				// });
				// dataTableEFCR.draw();
			}
		});
	});

	formInventoryComments.addEventListener('submit', e => {
		e.preventDefault();
		const formData = new FormData(e.target);
		
		formData.append('env', 'services');
		formData.append('call', 'doSetComments');
		formData.append('id', btnCommentsModal.dataset.device_id);

		const body = formToArr(formData);
		sendRequest('POST', requestURL, body).then((data) => {
			showComments(btnCommentsModal.dataset.device_id, data);
		});
		e.target.reset();
	});

	devicesAllBody.addEventListener('click', deviceActionMosaic);
	devicesAllBody.addEventListener('keydown', deviceKeyDown);
	btnDialogModal.addEventListener('click', confirmDialog);
	tInventory.addEventListener('click', (e) => {
		deviceActionNodes(e, {});
	});
	// tEFCR.addEventListener('click', (e) => {
	// 	deviceActionEFCR(e, {});
	// });
	// tEFCR.addEventListener('keydown', efcrKeyDown);
	// tEFCR.addEventListener('focusout', efcrFocusOut);
	
	const formToArr = (formData) => {
		const arrData = {};
		for (let [key, value] of formData.entries()) {
			if (typeof value == 'object') continue;
			arrData[key] = value.trim();
		}
		return arrData;
	};

	const clearAttachmentsArea = (container, attachmentsList) => {
		container.dataset.page_id = null;
		container.style.display = 'none';
		attachmentsList.textContent = '';
	};

	const setBookTitle = (bookName, book_id, container) => {
		if (bookName) {
			container.innerText = `${bookName}'s pages`;
			container.dataset.name = b64EncodeUnicode(bookName);
			container.dataset.full_name = b64EncodeUnicode(container.innerText);
			container.dataset.id = book_id;
		} else {
			container.innerText = '';
			container.dataset.name = null;
		}
	};

	const clearPageContent = (props = null) => {
		tinymce.activeEditor.setContent('');
		pageNameEdit.innerText = savedPageName;
		page_id = '0';
		clearAttachmentsArea(newAttachment, bookAttachmentsList);
		if (props && props.bookName) {
			setBookTitle(props.bookName, props.id, pagesTitle);
		}
	};

	const showOGPA = (data, extends_data) => {
		selPrimeElement.textContent = '';
		if (data && data.success && data.success.answer) {
			data.success.answer.forEach(item => {
				let selected = '';
				if (!!extends_data && extends_data['element'] === item.element) {
					selected = 'selected';
				}
				
				selPrimeElement.insertAdjacentHTML('beforeend', `
					<option data-id="${item.id}" value="${item.element}" ${selected}>${item.element}</option>
				`);
			});
			if (activity !== '') {
				selPrimeElement.dataset.activity = (typeof extends_data === 'undefined' || extends_data['activity'] === undefined) ? '' : extends_data['activity'];
			}
		} else {
			selPrimeElement.insertAdjacentHTML('beforeend', `
				<option value="" selected></option>
			`);
		}
		selPrimeElement.dispatchEvent(new Event('change'));
	};

	const showOGPAActivity = (data, extends_data = {}) => {
		selActivity.textContent = '';
		if (data && data.success && data.success.answer) {
			data.success.answer.forEach(item => {
				let selected = '';
				if (extends_data['activity'] === item.element) {
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
		document.querySelectorAll('fieldset.hidden').forEach(item => {
			if (adminEnabled && !item.classList.contains('js-hard-code-design')) {
				item.classList.remove('hidden');
			} else {
				item.classList.add('hidden');
			}
		});
		document.querySelectorAll('.renderOnly').forEach(item => {
			if (!item.classList.contains('js-hard-code-design')) {
				setAvailFormElements(item, !!adminEnabled);
			} else {
			}
		});

		efcrFields.value = "";
		const efcrFieldsArr = [];

		document.querySelectorAll('.js-eFCR-view').forEach(item => {
			if (templateDip) {
				item.querySelectorAll('input').forEach(inputElem => {
					inputElem.disabled = false;
					if (inputElem.type != 'hidden') {
						inputElem.classList.add(inputSelectorClass);
					}
					if (inputElem.dataset['efcr'] == "1") {
						efcrFieldsArr.push(inputElem.name);
					}
				});
			} else {
				item.querySelectorAll('input').forEach(inputElem => {
					inputElem.disabled = true;
					if (inputElem.type != 'hidden') {
						inputElem.classList.remove(inputSelectorClass);
					}
				});
			}
		});
		document.querySelectorAll('.js-eFCR2-view').forEach(item => {
			if (templateDip) {
				item.querySelectorAll('input').forEach(inputElem => {
					inputElem.disabled = false;
					if (inputElem.type != 'hidden') {
						inputElem.classList.add(inputSelectorClass);
					}
				});
				item.querySelectorAll('textarea').forEach(inputElem => {
					inputElem.disabled = false;
					if (inputElem.type != 'hidden') {
						inputElem.classList.add(inputSelectorClass);
					}
				});
			} else {
				item.querySelectorAll('input').forEach(inputElem => {
					inputElem.disabled = true;
					if (inputElem.type != 'hidden') {
						inputElem.classList.remove(inputSelectorClass);
					}
				});
				item.querySelectorAll('textarea').forEach(inputElem => {
					inputElem.disabled = true;
					if (inputElem.type != 'hidden') {
						inputElem.classList.remove(inputSelectorClass);
					}
				});
			}

		});
		efcrFields.value = JSON.stringify(efcrFieldsArr);
		if (data && data.success && data.success.answer) {
			data.success.answer.forEach(({groupID, hidden, fields, disabled=false}) => {
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
								if (fieldIn.type != 'hidden') {
									fieldIn.classList.add(inputSelectorClass);
								}
							}
						});
						if ((adminEnabled && !group.classList.contains('renderOnly')) || 
						(!hidden && !group.classList.contains('js-hard-code-design'))) {
							group.classList.remove('hidden');
						} else {
							group.classList.add('hidden');
							if (disabled) {
								group.disabled = true;
							}
						}
					}
				} catch (e) {
				}
			});
			// checkInputsData(`.${inputSelectorClass}`);
		}
		if (!!hardCodeDesign[gActivityName] && !adminEnabled && (document.title == 'Roaming FCR' || document.title == 'eFCR')) {
			showHardCodeDesign(hardCodeDesign[gActivityName].split(','));
		} else if (document.title == documentProperties.cSDEPingtest.title || 
			document.title == documentProperties.cSDEBundle.title
		) {
			showHardCodeDesign(hardCodeDesign['Capacity Upgrade'].split(','));
			// documentProperties
		}
		formAdmin.querySelectorAll('[data-ctemplate]').forEach( item => {
			if (cTemplate) {
				item.value = item.dataset.ctemplate.trim();
			} else {
				item.value = item.dataset.default.trim();
			}
		});
		checkInputsData(`.${inputSelectorClass}`);
	};

	const showChassisTags = (data) => {
		inventoryTagsSet.clear();
		if (data && data.success && data.success.answer) {
			data.success.answer.forEach(({id, tag}) => {
				inventoryTagsSet.add(tag);
			});
		}
		fillInventoryTags(inventoryTagsSet);

	};

	const iniOGPA = (extends_data = '') => {
		const body = {
			method: 'getOGPA',
			ogpa_group: cTemplate,
		};
		sendRequest('POST', requestURLTemplate, body).then((data) => {
			showOGPA(data, extends_data);
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
		resetHardCodeDesign();
		const body = {
			method: 'getActivityFields',
			id: activityID
		};
		sendRequest('POST', requestURLTemplate, body).then((data) => {
			showActivityFields(data);
		});
	};

	const iniProjects = () => {
		const body = {
			method: 'getGroupsList',
		};
		sendRequest('POST', requestURLProject, body).then(showGroupsProjectSection);
		body.method = 'getProjectsList';
		sendRequest('POST', requestURLProject, body).then(showProjectsList);
		
	};

	const iniInventory = () => {
		inventoryMode = 1;
		dataTableInventory.ajax.reload();
	};

	const getChassisTags = (chassis_id) => {
		const body = {
			method: 'getChassisTags',
			id: chassis_id,
		};
		sendRequest('POST', requestURLTemplate, body).then((data) => {
			showChassisTags(data);
		});
	};

	const setChassisTags = (chassis_id) => {
		const body = {
			method: 'setChassisTags',
			id: chassis_id,
			value: [...inventoryTagsSet],
		};
		sendRequest('POST', requestURLTemplate, body).then((data) => {
			showChassisTags(data);
		});
	};

	const delElement = (method, callback, value) => {
		const body = {
			method: method,
			value: value,
			ogpa_group: cTemplate,
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
			fieldGroup.disabled = false;
			let fieldsArr = [];
			for (let fieldEl of fieldset.elements) {
				if (fieldEl.tagName === 'INPUT' || 
					fieldEl.tagName === 'SELECT' || 
					fieldEl.tagName === 'TEXTAREA') {
					let fieldValue = fieldEl.value;
					if (fieldEl.type === 'checkbox') {
						fieldGroup.hidden = fieldEl.checked ? 0 : 1;
						if (fieldEl.classList.contains('js-pinned') && fieldGroup.hidden) {
							fieldGroup.disabled = true;
						}
						continue;
					}
					if (fieldEl.classList.contains('renderOnly')) {
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

	const submitRenderForm = (renderForm, gActivityID) => {
		formAdmin.querySelectorAll('.renderData').forEach(item => {
			const added_input = document.createElement("input");
			added_input.name = item.dataset['value_name'];
			added_input.value = item.value;
			added_input.type = "hidden";
			renderForm.append(added_input);
		});
		const added_ActivityID = document.createElement("input");
		added_ActivityID.name = 'activityID';
		added_ActivityID.value = gActivityID;
		added_ActivityID.type = "hidden";
		const added_counterMode = document.createElement("input");
		added_counterMode.name = 'counterMode';
		added_counterMode.value = gCounterMode;
		added_counterMode.type = "hidden";
		
		renderForm.append(added_ActivityID);
		renderForm.append(added_counterMode);
		
		const rows_object = {}
		renderForm.querySelectorAll("[data-parent='self']").forEach(item => {
			const rows_arr = [];
			item.closest('fieldset').querySelectorAll('.multirows').forEach(row_inputs => {
				const one_row = {};
				row_inputs.querySelectorAll('input').forEach(ceil_input => {
					if (ceil_input.name !== '') {
						one_row[ceil_input.dataset['name']] = ceil_input.value;
					}
				});
				row_inputs.querySelectorAll('select').forEach(ceil_input => {
					one_row[ceil_input.dataset['name']] = ceil_input.value;
				});
				row_inputs.querySelectorAll('textarea').forEach(ceil_input => {
					one_row[ceil_input.dataset['name']] = ceil_input.value.split('\n').filter(function(str) { return str.trim().length > 6 ? true : false});
				});
				rows_arr.push(one_row);
			});
			const item_name = item.dataset.id;
			rows_object[item_name] = rows_arr;
			item.closest('fieldset').querySelector(`#${item_name}`).value = JSON.stringify(rows_object[item_name]);
		});
		setImpactedNCTList();
		renderForm.submit();
		formSubmit.classList.remove('edit');
	};

	const checkInputsData = (inputsSelector, setIni = true) => {
		totalInputs = 0;
		changedInputs = 0;
		document.querySelectorAll(inputsSelector).forEach(item => {
			if (item.type !== 'hidden' && item.type !== 'file' && item.name !== '' && !(item.closest('fieldset').disabled))
			{
				totalInputs++;
				if (setIni) {
					item.dataset.ini_data = item.value.trim().substring(0, 20);
				}
				if (item.value.trim() !== '') {
					changedInputs++;
				}
			}
		});
		const progress = Math.trunc((changedInputs / totalInputs) * 100);
		divCounter.style.width = `${progress}%`;
		divCounter.innerText = `${progress}% (${changedInputs} of ${totalInputs})`;
		setImpactedNCTList();
	};

	const setImpactedNCTList = () => {
		function createNCTForItem(fieldSet, ...itemSelector) {
			let NCTList = '';
			let impactedSites = new Set();
			for (let item of itemSelector) {
				fieldSet.querySelectorAll(item).forEach(foundValue => {
					if (foundValue.value.trim() != '') {
						impactedSites.add(foundValue.value);
					} 
				});
			}
			for (const site of impactedSites) {
				NCTList += site + '\n';
			}
			return NCTList;
		}

		let impactedSites = new Set();
		if (document.title == 'eFCR') {
			impactedNCT.value = '';
			const fieldset = document.querySelector('.js-eFCR2-view');
			fieldset.querySelectorAll('select[data-name="dipPHUBSites"]').forEach(phubsite => {
				if (phubsite.value.trim() != '') {
					impactedSites.add(phubsite.value);
				} 
			});
			for (const site of impactedSites) {
				impactedNCT.value += site + '\n';
			}
		} else if (document.title == documentProperties.cSDEPingtest.title || 
			document.title == documentProperties.cSDEBundle.title) {
			const fieldset = document.querySelector('.js-cSDEPingTest-view');
			impactedNCT.value = createNCTForItem(fieldset, 'select[data-name="rcbin_node"]', 'select[data-name="csde_node"]');
		}
	};

	const resetHardCodeDesign = () => {
		for (const value of Object.values(hardCodeDesign)) {
			const classesHide = value.split(',');
			classesHide.forEach(item => {
				try {
					const fieldset = document.querySelector(`.${item.trim()}`);
					setAvailFormElements(fieldset, true);
				} catch (e) {
				}
			});
		}
	};

	const showHardCodeDesign = (classesList) => {
		classesList.forEach(item => {
			try {
				const fieldset = document.querySelector(`.${item.trim()}`);
				setAvailFormElements(fieldset, false);
			} catch (e) {
			}
		});
	};

	const showSuperUserElems = () => {
		visibleSuperOnly.forEach(item => {
			item.classList.remove('hidden');
		});
	};

	const hideSuperUserElems = () => {
		visibleSuperOnly.forEach(item => {
			item.classList.add('hidden');
		});	
	}

	const showRequestError = (errorMessage) => {
		containerError.innerText = errorMessage;
		containerError.classList.remove('d-none');
	}

	const importOGPA = ({element, acivity}) => { 
		const formData = new FormData(formAdmin);
		formData.append('method', 'importFromJSON');
		formData.append('primeElement', element);
		formData.append('acivity', acivity);

		sendFile('POST', requestURLTemplate, formData).then((data) => {
			showActivityFields(data);
		});

	};

	const fillInventoryTags = (tags) => {
		inventoryTags.textContent = '';
		for (let tag of tags) {
			inventoryTags.insertAdjacentHTML('beforeend', `
				<span class="inventory-tag" data-tag="${tag}">${tag}
					<a href="#" class="delete-tag">x</a>
				</span>
			`);
		}
		inventoryTags.insertAdjacentHTML('beforeend', `
			<span class="inventory-tag new-tag" contenteditable>&nbsp;</span>
		`);
	};

	formAdmin.addEventListener('reset', (e) => {
		const target = e.target;
	});

	formFields.addEventListener('reset', (e) => {
		const target = e.target;
		target.querySelectorAll('fieldset').forEach(fieldset => {
			// fieldset.querySelectorAll('.multirows[data-clone="1"]').forEach(row => {
			// 	row.remove();
			// });
			removeChildElementFromCollection(fieldset, '.multirows[data-clone="1"]');
			fieldset.querySelectorAll("[data-parent='self']").forEach(ceil => {
				ceil.value = 1;
				fieldset.querySelector(`#${ceil.dataset.id}`).value = "";
			})
		});
		formSubmit.classList.remove('edit');
		showAll.checked = false;
	});

	formFields.addEventListener('submit', (e) => {
		e.preventDefault();
		const target = e.target;
		if (adminEnabled) {
			submitAdminForm(target, gActivityID);
		} else {
			submitRenderForm(target, gActivityID);
		}
	});

	formFields.addEventListener('focusout', (e) => {
		e.preventDefault();
		const target = e.target;
		if (target.classList.contains(inputSelectorClass)) {
			try {
				if (target.value.trim().substring(0, 20) != target.dataset.ini_data.trim().substring(0, 20))
				{
					formSubmit.classList.add('edit');
					checkInputsData(`.${inputSelectorClass}`, false);
				}
			} catch (e) {
			}
		}
	});

	formFields.addEventListener('click', (e) => {
		const target = e.target;
		if (target.type === "checkbox" && target.dataset.ini_data !== target.checked) {
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
			ogpa_group: cTemplate,
			id: id,
		};
		sendRequest('POST', requestURLTemplate, body).then((data) => {
			if (data && data.success && data.success.answer) {
				showOGPA(data, {'element':newPrimeElem.value});
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
				showOGPAActivity(data, {'activity':newActivity.value});
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
			const activity = target.dataset.activity;
			switchToNew(btnEditPrimeElem);
			iniOGPAActivity(id, {'activity':activity});
		} catch (e) {
			iniOGPAActivity(0);
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
		gActivityName = target.value;
		newActivity.value = '';
		switchBtnMode({
			elem: btnEditActivity,
			newMode: 1,
			editableName: '', 
			btnType: 'new', 
			btnText: 'Add new'});
		getActivityFields(id);
	});

	groupsList.addEventListener('change', (e) => {
		e.preventDefault();
		const target = e.target;
		fillGroupUsers(target.options[target.selectedIndex].dataset.group_id);
	});

	const comboSelectChange = (e) => {
		const target = e.target;
		const parent = target.closest('div');
		const parent_row = parent.closest('.multirows');
		const input_elem = parent.querySelector(`.editOption`);
		if (target.options[target.selectedIndex].classList.contains('editable')) {
			input_elem.classList.remove('hidden');
			input_elem.addEventListener('keyup', (e) => {
				target.options[target.selectedIndex].value = input_elem.value;
				target.options[target.selectedIndex].textContent = input_elem.value;
				target.value = input_elem.value;
			});
		} else {
			input_elem.classList.add('hidden');
		}
		if (target.options[target.selectedIndex].value === 'ICMP') {
			parent_row.querySelector(`[data-name='${target.dataset.port}']`).value = '';
		}
	};

	comboSelect.forEach(element => {
		element.addEventListener('change', comboSelectChange); 
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
				if (!item.classList.contains('js-hard-code-design'))
				{
					item.classList.remove('hidden');
					item.classList.add('showned');
				}
			});
		} else {
			document.querySelectorAll('fieldset.showned').forEach(item => {
				item.classList.add('hidden');
				item.classList.remove('showned');
			});
		}
	});

	// aExport.addEventListener('click', (e) => {
	// 	e.preventDefault();
	// 	const body = {
	// 		method: 'exportToJSON',
	// 		primeElement: selPrimeElement.value,
	// 		activity: selActivity.value,
	// 	};
	// 	sendRequest('POST', requestURLTemplate, body).then((data) => {
	// 		exportDownload.classList.add('hidden');
	// 		if (data && data.success && data.success.answer) {
	// 			const temp_href = exportDownload.querySelector('A');
	// 			temp_href.href = data.success.answer;
	// 			temp_href.click();
	// 		} else {
	// 			showRequestError('Export to JSON unsuccessful');
	// 		}
	// 	});
	// });

	// aImport.addEventListener('change', (e) => {
	// 	e.preventDefault();
	// 	const target = e.target;
	// 	showModalDialog({
	// 		attributes: [
	// 			{'modal-command': 'importOGPA'},
	// 			// {'data-file-name': target.files[0].name},
	// 			{'data-element': selPrimeElement.value},
	// 			{'data-activity': selActivity.value},
	// 		],
	// 		dialogTitle: 'Import OGPA config',
	// 		dialogQuestion: `Do you want to import OGPA from JSON (current settings will be overwritten)?`,
	// 	});
	// });

	const btnAreaAppend = (target) => {
		const fieldset = target.closest('fieldset');
		const row = fieldset.querySelector("[data-parent='self']");
		let row_prime;
		let copyArea = false;
		if (target.dataset.copy !== undefined) {
			row_prime = target.closest('.multirows');
			copyArea = true;
		} else {
			row_prime = fieldset.querySelector('.multirows');
		}
		const new_row = row_prime.cloneNode(true);
		row.value = parseInt(row.value) + 1;
		new_row.dataset.clone = 1;
		new_row.querySelectorAll('input').forEach(item => {
			item.name = `${item.dataset.name}_${row.value}`;
			item.id = item.name;
			if (!copyArea) {
				item.value = '';
				item.dataset.ini_data = '';
			}
		});
		new_row.querySelectorAll('textarea').forEach(item => {
			item.name = `${item.dataset.name}_${row.value}`;
			item.id = item.name;
			if (!copyArea) {
				item.textContent = '';
				item.dataset.ini_data = '';
			}
		});
		new_row.querySelectorAll('select').forEach(item => {
			item.name = `${item.dataset.name}_${row.value}`;
			item.id = item.name;
			if (copyArea) {
				item.selectedIndex = row_prime.querySelector(`[data-name="${item.dataset.name}"]`).selectedIndex;
			}
			item.dataset.ini_data = item.options[item.selectedIndex].value;
		});
		const cloneButton = new_row.querySelector('[data-copy]');
		if (cloneButton !== null) {
			cloneButton.dataset.copy = parseInt(cloneButton.dataset.copy) + 1;
			cloneButton.addEventListener('click', (e) => {
				btnAreaAppend(e.target);
			});
		}
		new_row.querySelectorAll('.combo-select').forEach(element => {
			element.addEventListener('change', comboSelectChange);
			element.dispatchEvent(selectCnange);
		});
		fieldset.append(new_row);
		fieldset.append(fieldset.querySelector('.ceil-btns'));
		checkInputsData(`.${inputSelectorClass}`, false);
		return new_row;
	};

	btnsCeilAreaAppend.forEach(item => {
		item.addEventListener('click', (e) => {
			btnAreaAppend(e.target);
		});
	});

	btnsCeilAreaRemove.forEach(item => {
		item.addEventListener('click', (e) => {
			const fieldset = e.target.closest('fieldset');
			const cloned_rows = fieldset.querySelectorAll("[data-clone='1']");
			if (cloned_rows.length) {
				cloned_rows[cloned_rows.length - 1].remove();
			}
			checkInputsData(`.${inputSelectorClass}`, false);
		});
	});


	clearInputsForms();
	iniInterface();
	holdStatus.click();
});