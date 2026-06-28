**Стек:** PHP 7.4, Apache 2.4, MySQL

## Комментарии к JS

1. Новые права для пользователя пушатся в массив newRights.push, затем через modRights отправляются на сервер. Для pageName используется capitalize, что приводит к необходимости изменения регистра, например как cTemplate при выводе меню. В дальнейшем нужно оптимизировать этот алгоритм  
2. toggleSection переключает главный интерфейс на выбранный пункт меню. Для случая с MOP документами в нужную секцию section\[idx\].append(renderMopDiv) копируется содержимое "базового" шаблона с формой  
3. метод iniOGPA инициирует процесс получения данных по MOP документам, в первую очередь вызывая endpoint getOGPA на сервере, ключевой параметр \- ogpa\_group, который связан с одной из групп. Привязка групп \- достаточно жесткая в самом коде, например группа "Service Delivery Environment" как самая первая в разработке имеет id 0, и так далее. При вызове getOGPA вручную в коде JS определяется ее id, который в базе данных в таблице prime\_element имеет такой же id, т.е. при добавлении новой группы нужно предусмотреть ее id как в базе, так и в коде JS, Пока речь идет о 4-х группах. Для DDP ogpa\_group=100.  
4. Для DDP добавлен метод `resetMultirows`, который удаляет из редактирования все строки multirows\[data-clone="1"\]  
5. При добавлении новой секции меню, чтобы она правильно отображалась в интерфейсе нужно в index.html добавить секцию: \`\<section class="section templatetDIP"\>\</section\>\`   
6. Сохранение шаблона выполняется вызовом submitAdminForm, второй параметр которой \- activityID, который должен быть определен в базовых таблицах OGPA.  
7. Функция clearProjectActivity показывает форму редактирования проекта formNewProject и скрывает активность.  
8. Функция-заглушка toggleProjectsActivityArea сокращает программный код, показывает/прячет область данных проекта  
9. Когда все файлы проекта созданы, вызывается функция submitRenderForm с параметром complexDoc=2, чтобы получить от сервера полный файл проекта в формате docx  
10. Функция showActivityFields заполняет на форме поля для каждой группы через document.querySelector(\`\#${groupID}\`) в ответ на вызов API getActivityFields.  
11. ogpaDDP \- жестко заданное значение 100 для работы с DDP-страницей  
12. объект groupsDDP \- жестко заданный список соответствия между группами и prime\_element  
13. `groupInprojectGroups` \- найденная группа в списке проекта. Если такой элемент существует в DOM, то `addGroupToProject` не вызывается повторно.

### Функции JS

* `switchMORTables` \- переключает часть таблицы MOR в зависимости от списка mor\_type  
* `iniMOR` инициирует работу с MOR, получает данные из таблиц  
* `genRowParts` \- формирует строку таблицы для parts  
* `resetMORData` \- удаляет данные MOR из таблицы  
* `fillGroupUsers` \- заполняет список пользователей, входящих в группу проекта  
* `showGroupsProjectSection` \- инициализирует массив списком групп проекта и вызывает отображение этого списка  
*  `fillGroupsProject` \- заполняет `#group-project` группами, доступными для проекта  
* `showProjectsInfo` отображает линейку проекта. Внутри подсчитывается кол-во activity всего и завершенных (`finishedActivities`). Если они совпадают, проект считается завершенным, оформляется особым стилем, и для него можно скачать полный документ (пока \- реализовано скачивание отдельных документов для каждой activity-группы). В `projectActivitiesList` находятся все activity проекта, currentProjectReadyStatus имеет статус 1, когда все activity завершены.  
* `projectActivityGenerate` \- формирует элемент проекта из activity-групп.  
* `submitRenderForm` заполняет поля формы и получает от сервера готовый docx файл, либо если идет работа с готовым проектом, то получает от сервера имя файла и сохраняет его в projectDocsList  
* `datetimeToCaDate` \- возвращает текстовую дату в формате YYYY-MM-DD  
* `datetimeToUSDate` \- возвращает текстовую дату в формате \`May 16, 2026\`  
* setOGPADDP \- “хак” для создания списка groupsDDP в новой таблице (при переходе с лаб на прод)  
* `showProjectActivityForm` \- вызывает заполнение полей MOP для группы из проекта  
* `resetMorForm()` \- очищает форму MOR  
* `saveMORData` упаковывает форму MOR для отправки в виде JSON в базу, либо в одиночный MOR, либо в activity проекта  
* `showProjectMORForm` показывает форму MOR для проекта  
* `fillMORFields` заполняет форму MOR данными из базы  
* `fillUploadedFiles` заполняет подписи для полей загрузки файлов ZTM  
* `resetUploadedFiles` очищает подписи для полей загрузки файлов ZTM  
* `getProjectDocs` отправляет запрос на формирование архива с документами проекта  
* `setMultirowsParam` упаковывает многострочные элементы activity в скрытый элемент формы  
* `selectMenuItem` \- выделяет выбранный пункт меню, находя его по data-section внутри DOM-элемента menu  
* 

### События JS

* `selMorGroup.addEventListener('change')` \- при выборе группы запрашивает сохраненные данные  
* `projectsFullList.click` \- получает activity, связанную с проектом через вызов getProjectsActivityByID.  
* `morTable.addEventListener('click')` \- при клике на строку parts таблицы MOR копирует ее id в кнопку удаления строки morDelRow  
* morReset.addEventListener('click') \- вызывает удаление данных MOR из базы и очищает форму  
* `formFields.addEventListener('submit')` \- отправляет данные на сервер и получает готовый документ  
* `selPrimeElement.addEventListener('change')` \- получает activity при работе с MOP  
* `selGroupsDDP.addEventListener('change')` \- получает activity при работе с DDP  
* morGroupID.addEventListener('change') \- запрашивает данные таблицы MOR  
* `morSend.addEventListener('click')` открывает почтовый клиент и запрашивает готовую таблицу MOR  
* `morSave.addEventListener('click')` вызывает сохранение формы MOR в базу, с привязкой к определенной группе  
* `morSubmit.addEventListener('click')` вызывает сохранение формы MOR в базу со статусом ready, либо запрашивает готовую таблицу

## Комментарии к Базе данных

Таблица `mor_fields` хранит состояние MOR-полей для каждой группы.

``CREATE TABLE `mor_fields` (``  
    `` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ``  
    `` `group_id` BIGINT UNSIGNED NOT NULL DEFAULT '0', ``  
    `` `field_json_props` TEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci', ``  
    ``PRIMARY KEY (`id`) USING BTREE,``  
    ``INDEX `FK_mor_fields_groups` (`group_id`) USING BTREE,``  
    ``CONSTRAINT `FK_mor_fields_groups` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON UPDATE NO ACTION ON DELETE NO ACTION``  
`)`  
`COLLATE='utf8_general_ci'`  
`ENGINE=InnoDB`  
`;`

Таблица `projects_files` содержит файлы (изображения, вложения) для activity проекта  
``CREATE TABLE `projects_files` (``  
    `` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ``  
    `` `activity_id` BIGINT UNSIGNED NOT NULL DEFAULT '0', ``  
    `` `activity_target` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci', ``  
    `` `file_name` VARCHAR(100) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci', ``  
    ``PRIMARY KEY (`id`) USING BTREE,``  
    ``INDEX `FK__projects_activity` (`activity_id`) USING BTREE,``  
    ``CONSTRAINT `FK__projects_activity` FOREIGN KEY (`activity_id`) REFERENCES `projects_activity` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE``  
`)`  
`COLLATE='utf8_general_ci'`  
`ENGINE=InnoDB`  
`;`

## Комментарии к результирующему документу MOP

1. Файл mop\_blank.docx содержит в себе разрыв страницы, для добавления его между готовыми файлами проекта  
2. Для соединения файлов проекта в один используется библиотека DocxMerge\\DocxMerge. После создания общего документа промежуточные файлы удаляются

   

## Комментарии к index.html

Форма formAdmin и formFields содержат в себе поля, которые отправляются на бэк-энд и используются для заполнения шаблона готового документа. Описание полей formAdmin:

* actField34 \- имя группы. При переключении пункта меню в JS изменяется состояние глобальной переменной, и в зависимости от нее устанавливается значение поля. **Readonly**

	Описание полей formFields:

* \<fieldset id="groupX"\> \- группа полей, которая целиком отображается или нет в интерфейсе, если включен флаг. Внутри группы каждое поле имеет свой id, который соответствует полю в документе.

Описание полей formNewProject:

* input\_project\_id \- скрытое поле номера id проекта из таблицы projects\_activity

Список `mor_type` в параметре `data-mor-type` хранит состояние, через которое управляется вид таблицы MOR, показывается либо шаблон  `morForRelease` либо `morForOthers`.  
Форма `formAdminDDP` хранит поля для DDP. Для загрузки файлов и хранения их состояния используются поля   
`<input type="file" class="mop renderOnly" id="diagram_hl" name="diagram_hl" accept="image/png, image/jpeg" />`  
`<span class="mop bg-success js-project-files" data-file-id="diagram_hl"></span>`  
Когда от сервера приходит список файлов activity то в span выводится сообщение, что файл загружен.

Список `groupDDP` содержит выбор групп для DDP, при выборе группы изменяется значение ogpaDDP в скрипте

### Комментарии к PHP

### mor\_admin.php

| getMORUserGroups | список групп в которые входит пользователь. Для super \- доступны все группы. |
| :---- | :---- |
| saveMORData | сохраняет MOR в базу для выбранной группы, либо в activity проекта |
| resetMORData | удаляет MOR для группы |
| loadMORCA | загружает в базу справочные таблицы |
| getMORData | запрашивает данные из справочных таблиц, либо сохраненные данные формы для группы |
|  |  |
|  |  |
|  |  |

### classDatabase.php

| selectFieldFromTable | Получает значение указанного поля (для фильтрации использовать уникальное значение) |
| :---- | :---- |
| selectObjectFromTable | Получает объект (набор строк) которые соответствуют набору фильтров |
| removeObjectFromTableFilter | Удаляет строки на основе фильтра |
| runInsertSQL | Добавляет запись в таблицу |
| runUpdateSQL | Обновляет запись по указанному набору фильтров |
| setActivityFields | Устанавливает поля activity для MOP |

### classMor.php

| issetGroup | проверяет, есть ли в таблице сохраненные поля этой группы |
| :---- | :---- |
|  |  |
|  |  |
|  |  |

### classProjects.php

| getGroupsList | получает из базы список групп проекта с пользователями |
| :---- | :---- |
| getProjectsActivityAll | получает из базы активности всех проектов |
| getProjectsActivityByID | получает активность для выбранного проекта |
| addGroupToProject | добавляет группу(ы) в проект |
| getAvailGroupsList | список групп проекта, доступных для данного пользователя |
| `addFileToProjectActivity` | добавляет файл к activity проекта |
| `removeFileFromProjectActivity` | удаляет файлы из базы и диска для activity |
| `getFilesListFromProjectActivity` | получает список файлов для activity проекта |

## Подключение к БД Лаб

идем по адресу сервера, на порт PMA. Хост \- mysql.  
