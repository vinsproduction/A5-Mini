<?php
// Общий помощник для всего приложения в целом
// Здесь нужно описать функции, которые будут доступны для любого контроллера в приложении
// Чтобы описать функции нужные для какого-то конкретного контроллера в приложении, создайте в этой папке
// файл с названием <controller_name>_helper.php и опишите эти функции там - данный файл автоматически
// подключится при подключении контроллера с указанным именем.
// Также можно использовать стандартную функцию include_helper("name_of_controller") для подключения
// помощников предназначенных для других классов.



	/*	Рендер путой вььюхи с переданным в нее контеном, через глобальную переменную
		Удобно когда надо передать во view пару строк, не создавая при этом новую страницу 
		и оставляя layout
	*/	
	function render_content($content){
	
		render_view('/helpers/content', array("_CONTENT"=>$content));	
	
	}
	
	
	
	
	
	


