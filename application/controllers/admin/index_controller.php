<?

/* ПРИМЕР АДМИНКИ С ХЕЛПЕРОМ ФОРМЫ

if(action() == 'index')
{			

	$e_list = db_select_all('SELECT * FROM items');
	render_view('/admin/index');		
}




if(action() == 'add' || action() == 'edit')
{	
	
	if(action() == "edit" && !@$_POST)
	{
		$_POST = db_select_row('SELECT * FROM items WHERE id=?i', $_GET['id']);

	}
	
	$Form = new FormBuilder('items');

	$Form->label('Название');
	$Form->input('name')->value( @$_POST['name'] )->rule('empty','Пустое поле!');
	
	
	$Form->label('Загрузить файл');
	$Form->input('file')->type('file');
	$Form->input('filenamePOST')->type('hidden')->value( @$_POST['file'] );	
			
	$Form->label('Файл');	
	$Form->freefield('fileFromBD', ( !empty( $_POST['file'] ) ) ? '<a target ="_blank" href="/data/items/'.$_POST['file'].'">'.$_POST['file'].'</a>' : 'Файл не загружен');	


	$Form->buttonSave()->value('Сохранить')->hasClass('btn btn-inverse');
	

	if( $Form->post() ){
	
	
		if( !empty($_FILES['file']['name'])  ){
	
			$file = ImageClass::setObject($_FILES['file'])							
					->save('data/items');
			
			$_POST['file'] = $file->name.'.'.$file->ext;	
					
		}else{			
			$_POST['file'] = $_POST['filenamePOST'];				
		}
		
		if( @is_empty($_POST['file']) ) $Form->errorField('file','Файл не загружен!');	

		if($Form->ok()){

			if(action() == "add")  { 
			
				//varexp($Form->post());
			
				$id = db_insert('items', array('name'=>$_POST['name'],'file'=>$_POST['file']));
				redirect_to('/admin/edit?id='.$id); 
			}
			if(action() == "edit") {
				db_update('items', array('name'=>$_POST['name'], 'file'=>$_POST['file'])
				, array('id'=>$_GET['id']));
				redirect_to('/admin');			
			}
		}
		
	}
	
	render_view('/helpers/form');
}	
	
	
if(action() == 'delete')
{	
	db_delete('items', array('id'=>$_GET['id']));
	redirect_to('/admin');
}

*/