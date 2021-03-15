const requestURL = 'backend.php';
const ticketsContainer = document.querySelector('.tickets-container');
const modifyContainer = document.querySelector('.modify-container');
const attachmentsContainer = document.querySelector('.attachments-container');
const dividerArrow = document.querySelector('.divider-arrow');
const triangle = document.querySelector('.triangle');
const btnAddTask = document.querySelector('.btn-add-task');
const btnUpdateTask = document.querySelector('.btn-update-task');
const btnCreateTaskFile = document.querySelector('.btn-add-file');
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
		// console.log(data);
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
					${filesAttached(files)}
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

function showAddedFile()
{
	console.log('file added!');
}
const filesAttached = (filesArray) => {
	if (filesArray.length) {
		const filesAttachedView = `
		<span class="file-attach">
			<img class="files-list" src="img/attach_file-small.svg" alt="file attached" data-files_list="${escapeHTML(JSON.stringify(filesArray.map(file => JSON.stringify(file))))}"/>
		</span>`;
		// console.log(filesAttachedView);
		return filesAttachedView;
	} else {
		return '';
	}
};


const escapeHTML = (string) => {
	return String(string).replace(/[&<>"'`=\/]/g, (s) => entityMap[s]);
};

const getAllTask = () => {
	hideEditablePanel();
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
	const body = {
		method: 'createTaskFile',
		params: {
			id: btnUpdateTask.dataset['task_id'],
		},
	}
	sendRequest('POST', requestURL, body).then(showAddedFile);
}
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
	btnUpdateTask.dataset['task_id'] = 0;
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
		const filesList = hrefAction.querySelector('.files-list');
		
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
			JSON.parse(filesList.dataset['files_list']).forEach(function (fileInfo) 
			{
				let { file_name, file_size } = JSON.parse(fileInfo);
				// console.log(JSON.parse(fileInfo));
				attachmentsContainer.insertAdjacentHTML('beforeend', `
					<p>File name: ${file_name}, file size: ${file_size}</p>
				`);
			});
		}	
		ticketTitle.value = taskTitle.textContent;
		btnUpdateTask.dataset['task_id'] = taskID;
		btnUpdateTask.classList.remove('d-none');
		btnAddTask.classList.add('d-none');
		viewEditablePanel();
	}
};

triangle.addEventListener('click', triangleToggle);
btnAddTask.addEventListener('click', createTask);
btnUpdateTask.addEventListener('click', updateTask);
btnClearSettings.addEventListener('click', clearEditableFields);
btnCreateTaskFile.addEventListener('click', createTaskFile);


getAllTask();

