const requestURL = 'backend.php';
const ticketsContainer = document.querySelector('.tickets-container');
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

const textCreatorHeader = 'Submitted by: ';

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

btnCreateTaskFile.value = '';

const timestampToDate = (timestampValue) => {
  const a = new Date(timestampValue * 1000);
  const months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
  return `${a.getFullYear()}-${months[a.getMonth()]}-${addZero(a.getDate())} ${addZero(a.getHours())}:${addZero(a.getMinutes())}:${addZero(a.getSeconds())}`;
}

const addZero = (i) => {
	if (i < 10) {
    i = "0" + i;
  }
  return i;
}

async function sendRequest(method, url, body = null) {
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
	ticketsContainer.textContent = '';
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

function showAddedTask()
{
	getAllTask();
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


const escapeHTML = (string) => {
	return String(string).replace(/[&<>"'`=\/]/g, (s) => entityMap[s]);
};

const removeElement = (domElement) => {
    if (!!domElement) {
		return domElement.parentNode.removeChild(domElement);
	}
};

const getAllTask = () => {
	// hideEditablePanel();
	clearEditableFields();
	const body = {
		method: 'getAllTasks',
	}
	sendRequest('POST', requestURL, body).then(showAllTasks);
};
const createTask = () => {
	const body = {
		method: 'createTask',
		params: {
			title: ticketTitle.value,
			description: ticketDescription.innerText,
			creator:ticketCreator.value,
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
			id: btnUpdateTask.dataset['task_id'],
		},
	}
	sendRequest('POST', requestURL, body).then(showAddedTask);
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

const clearEditableFields = () => {
	ticketTitle.value = '';
	ticketCreator.value = '';
	ticketDescription.textContent = '';
	btnUpdateTask.classList.add('d-none');
	btnAddTask.classList.remove('d-none');
	attachmentsArea.classList.add('invisible');
	btnUpdateTask.dataset['task_id'] = 0;
};

const fillFileInfo = (fileInfo) => {
	let { file_id, file_name, file_size } = JSON.parse(fileInfo);
	attachmentsContainer.insertAdjacentHTML('beforeend', `
		<p class="file-delete-container">File name: ${file_name}, Size: ${file_size}
			<img class="file-delete" src="img/delete.svg" atl="Delete file" data-file_id="${file_id}" title="Delete file"/>
		</p>
	`);
}
const actionTask = (event) => {
	const target = event.target;
	const hrefAction = target.closest('.task-ticket');
	if (!!hrefAction)
	{
		const taskTitle = hrefAction.querySelector('.task-title');
		const taskDescription = hrefAction.querySelector('.task-description');
		const taskID = hrefAction.dataset['task_id'];
		const positionCreator = taskDescription.innerText.lastIndexOf(textCreatorHeader);
		const filesList = hrefAction.querySelector('.files-list');
		fileAttach = hrefAction.querySelector('.file-attach');
		
		if (positionCreator !== -1) {
			ticketCreator.value = taskDescription.innerText.substring(positionCreator + textCreatorHeader.length);
			ticketDescription.innerText = taskDescription.innerText.substring(0, positionCreator);
		}
		else {
			ticketCreator.value = '';
			ticketDescription.innerText = taskDescription.innerText;
		}
		attachmentsContainer.textContent = '';
		if (!!filesList) {
			JSON.parse(filesList.dataset['files_list']).forEach(fillFileInfo);
		}
		// else {
		// 	attachmentsContainer.dataset['files_count'] = 0;
		// }
		ticketTitle.value = taskTitle.textContent;
		btnUpdateTask.dataset['task_id'] = taskID;
		btnUpdateTask.classList.remove('d-none');
		btnAddTask.classList.add('d-none');
		attachmentsArea.classList.remove('invisible');
		viewEditablePanel();
	}
};

triangle.addEventListener('click', triangleToggle);
btnAddTask.addEventListener('click', createTask);
btnUpdateTask.addEventListener('click', updateTask);
btnClearSettings.addEventListener('click', clearEditableFields);
attachmentsContainer.addEventListener('click', attachmentsAction);

btnCreateTaskFile.addEventListener('change', createTaskFile);


getAllTask();
viewEditablePanel();

