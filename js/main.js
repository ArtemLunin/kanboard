const requestURL = 'backend.php';
const ticketsContainer = document.querySelector('.tickets-container');
const containerError = document.querySelector('.container-error');
const modifyContainer = document.querySelector('.modify-container');
const attachmentsArea = document.querySelector('.attachments-area');
const attachmentsContainer = document.querySelector('.attachments-container');
const dividerArrow = document.querySelector('.divider-arrow');
const triangle = document.querySelector('.triangle');
const btnAddTask = document.querySelector('.btn-add-task');
const btnUpdateTask = document.querySelector('.btn-update-task');
const btnCreateTaskFile = document.querySelector('#attachFile');
const btnClearSettings = document.querySelector('.btn-clear-settings');
const ticketTitle = document.querySelector('.ticket-title');
const ticketCreator = document.querySelector('.ticket-creator');
const ticketDescription = document.querySelector('.ticket-description');
const ticketOTL = document.querySelector('.ticket-OTL');

//excel DOM
const periodSelect = document.querySelector('.period-select');
const pageData = document.querySelector('.page-data');
const tableExcel = document.querySelector('.table-excel');
const btnUpdateTicket = document.querySelector('.btn-update-ticket');
const ticketDescr = document.querySelector('.ticket-descr');
const btnRemove = document.querySelector('.btn-remove');
const ticketEditForm = document.querySelector('#ticketEditForm');

const inputName = document.querySelector('#inputName');
const inputDate = document.querySelector('#inputDate');
const inputDescr = document.querySelector('#inputDescr');
const inputTicket = document.querySelector('#inputTicket');
const inputCapOp = document.querySelector('#inputCapOp');
const inputOracle = document.querySelector('#inputOracle');

const textCreatorHeader = 'Submitted by:';
const textOTLHeader = 'OTL:';

const taskAttachments = [];

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

let fileAttach;
let fileDeleted = 0;
let periodDays = 1;


// for sort in ORDER DESC
const byField = (field) => {
	return (a, b) => a[field] > b[field] ? -1 : 1;
}

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
}

async function sendRequest(method, url, body, showWait = false) {
	const headers = {
		'Content-Type': 'application/json'
	};
	if(showWait) {
		$('#waitModal').modal('show');
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

function showAllTasks(data)
{
	if(!!data.success) {
		ticketsContainer.textContent = '';
		data.success.answer.sort(byField('date_creation'));
		data.success.answer.forEach(function ({
			id, creator_id, date_completed, date_creation, description, title, files
		}) 
		{
			ticketsContainer.insertAdjacentHTML('beforeend', `
				<div class="task-ticket" data-task_id="${id}">
					<a href="#" class="task-action" data-task_id="${id}">#${id}</a>
					<h6 class="task-title">${title}</h6>
					<p class="task-description">${description}</p>
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
}

function showAddedTask(taskCreateResult)
{
	btnUpdateTask.dataset['task_id'] = taskCreateResult.success.answer.id;
	attachmentsContainer.textContent = '';
	toggleToUpdateMode();
	getAllTask();
}

function showUpdatedTask(taskCreateResult)
{
	clearEditableFields();
	getAllTask();
}

function showUpdatedTaskFull()
{
	clearTicketFields();
	getBoard();
}

function showAddedFile(resultFileList)
{
	if (!!resultFileList.success.answer) {
		const taskID = resultFileList.success.answer.id;
		const fileTaskInfo = document.querySelector('#task_id_'+taskID);
		fileTaskInfo.innerHTML = filesAttached(resultFileList.success.answer.files);
		attachmentsContainer.textContent = '';
		(resultFileList.success.answer.files).forEach(fileItem => fillFileInfo(JSON.stringify(fileItem)));
	}
}

const removeFileFromList = (resultFileList) => {
	if (!!resultFileList.success.answer) {
		if (fileDeleted != 0) {
			removeElement(fileDeleted);
			fileDeleted = 0;
			const taskID = resultFileList.success.answer.id;
			const fileTaskInfo = document.querySelector('#task_id_'+taskID);
			fileTaskInfo.innerHTML = filesAttached(resultFileList.success.answer.files);
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

const showBoardTable = (data) => {
	if(!!data.success) {
		tableExcel.textContent = '';
		data.success.answer.forEach(function ({
			id, date_due, description, fields, assignee_name
		}) 
		{
			const descr_spaces = cr2spaces(description);
			tableExcel.insertAdjacentHTML('beforeend', `
				<tr class="task-ticket ${hideTask(date_due)}" data-task_id="${id}" data-date_due="${date_due}">
					<td class="ticket-date" data-item_value="${timestampToDate(date_due, false)}" data-item_id="inputDate">${timestampToDate(date_due, false)}</td>
					<td class="ticket-name" data-item_value="${assignee_name ?? '&nbsp;'}" data-item_id="inputName">${assignee_name ?? '&nbsp;'}</td>
					<td class="ticket-descr" data-item_value="${descr_spaces}" data-item_id="inputDescr">${descr_spaces}</td>
					<td class="ticket-ticket" data-item_value="${fields['ticket']}" data-item_id="inputTicket">${fields['ticket']}</td>
					<td class="ticket-capop" data-item_value="${fields['capop']}" data-item_id="inputCapOp">${fields['capop']}</td>
					<td class="ticket-oracle" data-item_value="${fields['oracle']}" data-item_id="inputOracle">${fields['oracle']}</td>
					<td class="text-center">
						<a href="#"><img class="icon-edit" src="img/edit.svg"></a>
					</td>
					<td class="text-center">
						<a href="#"><img class="icon-delete" src="img/delete.svg"></a>
					</td>
				</tr>
			`);
		});
		tableExcel.addEventListener('click', editTask);
	}
	$('#waitModal').modal('hide');
};

const fillUsersList = (data) => {
	if(!!data.success) {
		inputName.textContent = '';
		data.success.answer.forEach(function ({user_name}) {
			inputName.insertAdjacentHTML('beforeend', `
				<option value="${user_name}">${user_name}</option>
			`);
		});
	}
	inputName.value = "";
};

const editTask = (e) => {
	e.preventDefault();
	const target = e.target;
	if(target.classList.contains('icon-edit')) {
		const taskTicket = target.closest('.task-ticket');
		if(!!taskTicket) {
			clearTicketFields();
			selectTicketRow(taskTicket);
			const taskID = taskTicket.dataset['task_id'];
			for(const item of taskTicket.children)
			{
				const itemValue = item.dataset['item_value'];
				const inputID = item.dataset['item_id'];
				if(!itemValue || !inputID) continue;
				document.querySelector(`#${inputID}`).value = itemValue;
			}
			btnUpdateTicket.dataset['task_id'] = taskID;
			btnUpdateTicket.disabled = false;
		}
	} else if(target.classList.contains('icon-delete')) {
		const taskTicket = target.closest('.task-ticket');
		if(!!taskTicket) {
			const taskID = taskTicket.dataset['task_id'];
			btnRemove.dataset['task_id'] = taskID;
			ticketDescr.textContent = taskTicket.querySelector('.ticket-descr').dataset['item_value'];
			$('#modalRemoveDialog').modal('show');
		}
	}
};

const escapeHTML = (string) => {
	return String(string).replace(/[&<>"'`=\/]/g, (s) => entityMap[s]);
};

const removeElement = (domElement) => {
    if (!!domElement) {
		return domElement.parentNode.removeChild(domElement);
	}
};

const getAllTask = () => {
	const body = {
		method: 'getAllTasks',
	}
	sendRequest('POST', requestURL, body).then(showAllTasks);
};

const getBoard = () => {
	const body = {
		method: 'getBoard',
	}
	sendRequest('POST', requestURL, body, true).then(showBoardTable);
};

const getUsers = () => {
	const body = {
		method: 'getAssignableUsers',
	}
	sendRequest('POST', requestURL, body).then(fillUsersList);
};

const createTask = () => {
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
		},
	}
	sendRequest('POST', requestURL, body).then(showAddedTask);
};

const updateTask = () => {
	const body = {
		method: 'updateTask',
		params: {
			title: ticketTitle.value,
			description: ticketDescription.innerText,
			creator:ticketCreator.value,
			OTL: ticketOTL.value,
			id: btnUpdateTask.dataset['task_id'],
		},
	}
	sendRequest('POST', requestURL, body).then(showUpdatedTask);
};

const updateTaskFull = () => {
	if(btnUpdateTicket.dataset['task_id'] != 0) {
		const body = {
			method: 'updateTaskFull',
			params: {
				description: spaces2cr(inputDescr.value),
				id: btnUpdateTicket.dataset['task_id'],
				user_name: inputName.value,
				date_due: inputDate.value.trim(),
				ticket: inputTicket.value.trim(),
				capop: inputCapOp.value.trim(),
				oracle: inputOracle.value.trim(),
			},
		}
		sendRequest('POST', requestURL, body, true).then(showUpdatedTaskFull);
	}
};

const removeTask = () => {
	$('#modalRemoveDialog').modal('hide');
	const body = {
		method: 'removeTask',
		params: {
			id: btnRemove.dataset['task_id'],
		},
	}
	sendRequest('POST', requestURL, body, true).then(showUpdatedTaskFull);
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
	// const parentFileAction = target.closest('.file-delete-container');
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


const triangleToggle = (event) => {
	const target = event.target;
	if (target.classList.contains('triangle-down'))
	{
		viewEditablePanel();
	}
	else {
		hideEditablePanel();
	}
};

const viewEditablePanel = () => {
	triangle.classList.remove('triangle-down');
	triangle.classList.add('triangle-up');
	$('.collapse').collapse('show');
}

const hideEditablePanel = () => {
	triangle.classList.add('triangle-down');
	triangle.classList.remove('triangle-up');
	$('.collapse').collapse('hide');
};
const toggleToUpdateMode = () => {
	btnUpdateTask.classList.remove('d-none');
	btnAddTask.classList.add('d-none');
	attachmentsArea.classList.remove('invisible');
};

const clearEditableFields = () => {
	ticketTitle.value = '';
	ticketCreator.value = '';
	ticketOTL.value = '';
	ticketDescription.textContent = '';
	btnUpdateTask.classList.add('d-none');
	btnAddTask.classList.remove('d-none');
	attachmentsArea.classList.add('invisible');
	btnUpdateTask.dataset['task_id'] = 0;
};

const clearTicketFields = () => {
	ticketEditForm.reset();
	inputCapOp.value = "";
	inputName.value = "";
	btnUpdateTicket.dataset['task_id'] = 0;
	btnUpdateTicket.disabled = true;
};

const selectTicketRow = (taskTicket)=> {
	const taskTickets = document.querySelectorAll('.task-ticket');
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
		if(isNaN(periodDays) || periodDays > 365 || periodDays < 1) {
			periodDays = 1;
		}
		refreshBoardTable();
	}
};

const refreshBoardTable = () => {
	const taskTickets = document.querySelectorAll('.task-ticket');
	taskTickets.forEach(item => {
		if(hideTask(parseInt(item.dataset['date_due'], 10)) === 'd-none') {
			item.classList.add('d-none');
		} else {
			item.classList.remove('d-none');
		}
	});
};

const fillFileInfo = (fileInfo) => {
	let { file_id, file_name, file_size } = JSON.parse(fileInfo);
	attachmentsContainer.insertAdjacentHTML('beforeend', `
		<p class="file-delete-container">File name: ${file_name}, Size: ${file_size}
			<img class="file-delete" src="img/delete.svg" atl="Delete file" data-file_id="${file_id}" title="Delete file"/>
		</p>
	`);
};

const actionTask = (event) => {
	const target = event.target;
	const hrefAction = target.closest('.task-ticket');
	if (!!hrefAction)
	{
		const taskTitle = hrefAction.querySelector('.task-title');
		const taskDescription = hrefAction.querySelector('.task-description');
		const taskID = hrefAction.dataset['task_id'];
		const positionCreator = taskDescription.innerText.lastIndexOf(textCreatorHeader);
		const positionOTL = taskDescription.innerText.lastIndexOf(textOTLHeader);
		const filesList = hrefAction.querySelector('.files-list');
		let extDescriptionPosition = 0;
		let endPositionOTL = endPositionCreator = 0;
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
		btnUpdateTask.dataset['task_id'] = taskID;
		toggleToUpdateMode();
		viewEditablePanel();
	}
};



if(pageData && pageData.dataset['excel'] == '1') {
	btnUpdateTicket.dataset['task_id'] = 0;
	btnUpdateTicket.disabled = true;
	btnUpdateTicket.addEventListener('click', updateTaskFull);
	btnRemove.addEventListener('click', removeTask);
	periodSelect.addEventListener('click', periodChange);
	clearTicketFields();
	getUsers();
	getBoard();
} else {
	btnCreateTaskFile.value = '';
	triangle.addEventListener('click', triangleToggle);
	btnAddTask.addEventListener('click', createTask);
	btnUpdateTask.addEventListener('click', updateTask);
	btnClearSettings.addEventListener('click', clearEditableFields);
	attachmentsContainer.addEventListener('click', attachmentsAction);

	btnCreateTaskFile.addEventListener('change', createTaskFile);

	clearEditableFields();
	getAllTask();
	viewEditablePanel();
}





