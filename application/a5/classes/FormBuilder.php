<?
	/**	
		FORM BUILDING

		EXAMPLE:
		button можно не указывать - будет по умолчанию.
		Обязательно указывать id формы. Важно при работе со множеством форм на странице!
		
		$Form = new FormBuilder('article');
		
		$Form->label('Имя');
		$Form->input('name')->value('Vins')->rule('empty', 'Поле не может быть пустым');
		$Form->label('Фамилия');
		$Form->input('surname')->id('77')->value('Poll');
		$Form->input('hidden')->type('hidden');
		$Form->label('Обо мне');
		$Form->textarea('about')->css('width:200px,height:80px')->value('программер');
		
		$Form->buttonSave()->value('Отправить')->hasClass('btn btn-primary');
		
		$Form->label('Свободное поле');
		$Form->freefield('free','Это свободный текст который может быть чем угодно');
									
		exit($Form->render());
		
	*/


 class FormBuilder
 {
	public $formID = false;
	public $action = "";
		
	private $_fields = array();
	private $labels = array();
	
	private $input = false;
	private $textarea = false;
	private $radio = false;
	private $checkbox = false;
	private $buttonSave = false;
	private $label = false;		
	
	public $errors = array();	
	public $defaultFormCss = "";
	public $defaultInputCss = "width:400px";
	public $defaultCheckboxCss = "";
	public $defaultRadioCss = "";
	public $defaultTextareaCss = "width:400px;height:150px";
	public $defaultButtonCss = "width:150px;height:40px";
	public $defaultFieldCss  = "padding-top:10px;padding-bottom:10px";
	public $defaultErrorCss = "color:red";
	public $defaultButtonValue = "Сохранить";
	public $defaultButtonName = "buttonSave";
	
	


	public function __construct($formID=false){
	
		if(!$formID)  $this->setError('varible "formID" can not be empty');	
		
		$this->formID = isset($_POST['formID']) && ($_POST['formID'] == $this->formID) ? $this->formID : $formID;

		return $this;
	}
	
	static function form($formID=false)
	{
		return new FormBuilder($formID);
	}
	
	public function action($url)
	{
		if(!$url) $this->setError('method "action" : url can not be empty');
		$this->action = $url;
		return $this;
	}
	
	public function input($name=false)
	{	
		if(!$name) $this->setError('method "input" :  name can not be empty');	
		$this->name = $name;
		$label = ( $this->label == false) ? "" : $this->labels[$this->labelName];		
		$this->_fields[$this->formID][$this->name] = $label.'<input name="'.$this->name.'" style="'.$this->defaultInputCss.'" class="" id="" value=""  type="text" />';
		$this->input = true;
		$this->label = false;
		return $this;

	}
	
	public function textarea($name=false)
	{	
		if(!$name) $this->setError('method "textarea" :  name can not be empty');	
		$this->name = $name;
		$label = ( $this->label == false) ? "" : $this->labels[$this->labelName];	
		$this->_fields[$this->formID][$this->name] = $this->labels[$this->labelName].'<textarea name="'.$this->name.'" style="'.$this->defaultTextareaCss.'" class="" id=""></textarea>';		
		$this->textarea = true;
		$this->label = false;
		return $this;

	}
	
	/* EXAMPLE: ->select('test',array('1'=>'1','2'=>'2:selected','3'=>'3')) */
	public function select($name=false, $options = array())
	{
		if(!$name) $this->setError('method "select" :  name can not be empty');
		if(empty($options)) $this->setError('field "select" options array is empty');
		
		$option_str = "";
		foreach( $options as $value=>$option) {
			if( preg_match("/(.*?):selected/i", $option, $matches) ) 
			{
				$option = $matches[1];
				$option_str.= '<option value="'.$value.'" selected="selected">'.$option.'</option>'; 
			}else{
				$option_str.= '<option value="'.$value.'">'.$option.'</option>'; 
			}
		}
		
		$this->name = $name;
		$label = ( $this->label == false) ? "" : $this->labels[$this->labelName];	
		$this->_fields[$this->formID][$this->name] = $this->labels[$this->labelName].'<select name="'.$this->name.'" style="" class="" id="">'.$option_str.'</select>';		
		$this->select = true;
		$this->label = false;
		return $this;
	}

	public function checkbox($name=false)
	{	
		if(!$name) $this->setError('method "checkbox" :  name can not be empty');	
		$this->name = $name;
		$label = ( $this->label == false) ? "" : $this->labels[$this->labelName];	
		$this->_fields[$this->formID][$this->name] = $label.'<input name="'.$this->name.'" style="'.$this->defaultCheckboxCss.'" class="" id="" value=""  type="checkbox" />';
		$this->checkbox = true;
		$this->label = false;
		return $this;
	}
	
	public function radio($name=false)
	{	
		if(!$name) $this->setError('method "radio" :  name can not be empty');	
		$this->name = $name;
		$label = ( $this->label == false) ? "" : $this->labels[$this->labelName];	
		$this->_fields[$this->formID][$this->name] = $label.'<input name="'.$this->name.'" style="'.$this->defaultRadioCss.'" class="" id="" value=""  type="radio" />';
		$this->radio = true;
		$this->label = false;
		return $this;
	}
	
	
	public function check()
	{
		$this->_fields[$this->formID][$this->name] = preg_replace('/\/>/', 'checked="checked"=\/>', $this->_fields[$this->formID][$this->name]);
		return $this;
	
	}
	
	/* СВОБОДНЫЙ DIV в форме. Например, если надо отобразить картинку между полями 
	
		$Form->label('Картинка');	
		$Form->freefield('imageBD', ( !empty( $_POST['image'] ) ) ? '<img src="/data/relationships/'.$_POST['image'].'" alt="*" />' : 'Картинка не загружена');	
	
	
	*/
	public function freefield($name=false,$html=false)
	{	
		if(!$name) $this->setError('method "freefield" :  html can not be empty');	
		$this->name = $name;
		$label = ( $this->label == false) ? "" : $this->labels[$this->labelName];	
		$this->_fields[$this->formID][$this->name] = $label.$html;	
		$this->label = false;		
		return $this;

	}
	
	
	public function buttonSave($name=false)
	{
		
		$this->name = ($name != false) ? $name : $this->defaultButtonName;
		$this->defaultButtonName = $this->name;

		$this->_fields[$this->formID][$this->name] = '<input type="submit" name="'.$this->name.'" style="'.$this->defaultButtonCss.'" class="" id="" value="" />';		
		$this->buttonSave = true;
		$this->label = false;
		return $this;
	}
	
	
	
	public function id($id = false)
	{
		$this->_fields[$this->formID][$this->name] = preg_replace('/id=".*?"/', 'id="'.$id.'"/', $this->_fields[$this->formID][$this->name]);
		return $this;
	}
	
	public function type($type = false)
	{
		$this->_fields[$this->formID][$this->name] = preg_replace('/type=".*?"/', 'type="'.$type.'"/', $this->_fields[$this->formID][$this->name]);
		return $this;
	}

	
	/* FORMAT: $Form->input('url')->css('width:500px,hight:50px') */
	public function css($args = false)
	{
		if(!$args) $this->setError('css is bad');
	
		$args = func_get_args($args);
		$args = preg_replace("/,/",";",$args[0]);		
		$this->_fields[$this->formID][$this->name] = preg_replace('/style=".*?"/', 'style="'.$args.'"', $this->_fields[$this->formID][$this->name]);
		return $this;
	}
	
	public function hasClass($name = false)
	{
		$this->_fields[$this->formID][$this->name] = preg_replace('/class=".*?"/', 'class="'.$name.'"', $this->_fields[$this->formID][$this->name]);
		return $this;
	}
	
	
	public function value($value = false)
	{
		if($this->textarea)
		{
			$this->_fields[$this->formID][$this->name] = preg_replace('/<\/textarea>/', $value.'</textarea>', $this->_fields[$this->formID][$this->name]);
			$this->textarea = false;
		}
		
		if($this->input)
		{		
			$this->_fields[$this->formID][$this->name] = preg_replace('/value=".*?"/', 'value="'.$value.'"', $this->_fields[$this->formID][$this->name]);
			$this->input = false;
		}
		
		if($this->radio)
		{		
			$this->_fields[$this->formID][$this->name] = preg_replace('/value=".*?"/', 'value="'.$value.'"', $this->_fields[$this->formID][$this->name]);
			$this->radio = false;
		}
		
		if($this->checkbox)
		{		
			$this->_fields[$this->formID][$this->name] = preg_replace('/value=".*?"/', 'value="'.$value.'"', $this->_fields[$this->formID][$this->name]);
			$this->checkbox = false;
		}
		
		if($this->buttonSave)
		{
			$this->_fields[$this->formID][$this->name] = preg_replace('/value=".*?"/', 'value="'.$value.'"', $this->_fields[$this->formID][$this->name]);
			
		}
	
		
		return $this;
	}
	

	/* label ставится перед полем */
	public function label($label = false)
	{
		$this->label = true;
		$this->labelName = $label;
		$this->labels[$this->labelName] = "<b>".$label."</b> <span></span><br />";
	
		return $this;
	}
	
	
	/*  
		Если надо задать вручную Error, пишем как в примере, не используя метод rule()
		Обычно это бывает нужно когда например надо обработать отправку файла картинки
		Но (!) в таком случает обрабочик сабмита формы надо делать по типу:
		EXAMPLE:
		if( $Form->post() ){
			if( @is_empty($_POST['url']) ) $Form->errorField('url','Длинное описание не заполнено');			
			if($Form->ok()){				
				// сохраняем в базу
			}
		}		
	*/
	public function errorField($fieldName, $errorName)
	{			
		$this->_fields[$this->formID][$fieldName] = preg_replace('/<span><\/span>/', '<span style="'.$this->defaultErrorCss.'">'.$errorName.'</span>', $this->_fields[$this->formID][$fieldName]);
		$this->errors[$this->formID][$fieldName] = $errorName;	
		return $this;
	}
	
	/*  Валидация
			EXAMPLE
			$Form->input('url')->rule('empty', 'Пустой url')->rule('min=3,max=10')
	*/
	public function rule($name = false, $error = false)
	{	
		if( $this->post() )
		{	

			//Если ошибка уже есть для этого поля - выводим
			if( isset($this->errors[$this->formID][$this->name]) ) return $this;	

			switch ($name) {
				
				// ->rule()
				case(false) :
				
					if( is_empty($_POST[$this->name]) ) { 
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Не может быть пустым'; 						
					}
				
				break;
				
				// ->rule('empty') ====> OLD!
				case "empty":
		
					if( is_empty($_POST[$this->name]) ) { 
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Не может быть пустым'; 						
					}

				break;
				
				
				// ->rule('noempty')
				case "noempty":
		
					if( is_empty($_POST[$this->name]) ) { 
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Не может быть пустым'; 						
					}

				break;
				// ->rule('notempty')
				case "notempty":
		
					if( is_empty($_POST[$this->name]) ) { 
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Не может быть пустым'; 						
					}

				break;
			
				case "email":
	
					if( @preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $_POST[$this->name]) == false ){
							
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Неправильный email'; 
					}
				
				break;
						
				// Минимально-максимальное кол-во символов
				// ->rule('min=3,max=10')
				case ( preg_match('/min=\d+/', $name) || preg_match('/max=\d+/', $name) ):
				
					preg_match('/min=(\d+)/', $name, $matchesMin);
					preg_match('/max=(\d+)/', $name, $matchesMax);
			
					if( !empty($matchesMin) ) {
						if( strlen($_POST[$this->name]) < $matchesMin[1] ) { 
							$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимое кол-во символов не меньше '.$matchesMin[1]; 
						}				
					}
					if( !empty($matchesMax) ) {
						if( strlen($_POST[$this->name]) > $matchesMax[1] ) { 
							$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимое кол-во символов не больше '.$matchesMax[1]; 
						}				
					}			
				
				break;
				
				// ->rule('numbers')
				case "numbers":
	
					if( !preg_match('/^[0-9]+$/', $_POST[$this->name])){
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимы только цифры'; 
					}
				
				break;
				
				// ->rule('letters')
				case "letters":
	
					if( !preg_match('/^[a-zA-Zа-яА-Я]+$/ui', $_POST[$this->name])){
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимы только буквы'; 
					}
				
				break;
				// ->rule('letters_eng')
				case "letters_eng":
	
					if( !preg_match('/^[a-zA-Z]+$/', $_POST[$this->name])){
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимы только английские буквы'; 
					}
				
				break;
				// ->rule('letters_rus')
				case "letters_rus":
	
					if( !preg_match('/^[а-яА-Я]+$/ui', $_POST[$this->name])){
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимы только русские буквы'; 
					}
				
				break;
				
				// ->rule('username')
				case "username":
	
					if( !preg_match('/^[a-zA-Zа-яА-Я0-9_\s]+$/ui', $_POST[$this->name])){
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Разрешены только буквы, цифры и пробелы'; 
					}
				
				break;
				
				
				
				// ->rule('password')
				case "password":
	
					if( @is_empty($_POST[$this->name]) ) {
					
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Не может быть пустым';  
						
					}elseif( !preg_match('/^[a-zA-Z0-9_]{1,}$/', $_POST[$this->name])){
						$this->errors[$this->formID][$this->name] = ($error) ? $error : 'Допустимы только английские буквы, цифры и "_"'; 
					}
					
				break;
				
				
			
			}

		}

		return $this;
	}
	
	
	
	/* простой хелпер проверки post формы */
	public function post()
	{
		return (isset($_POST['formID']) && ($_POST['formID'] == $this->formID)) ? true : false;
		
	}

	
	/* Проверка валидации и отправка формы. Если все ок возвращает TRUE */
	
	public function submit()
	{
		if( $this->post() )
		{   
			unset($_POST[$this->defaultButtonName]);		
			return ( $this->ok() ) ? $_POST : false;
		}		
		return false;
	}

	/* Короткий статус упешной валидации */
	public function ok()
	{
		return ( empty($this->errors) ) ? true : false;
	}
	
	/* Вывод во View */
	public function render()
	{
		if($this->buttonSave == false) $this->buttonSave()->value($this->defaultButtonValue);

		if( !empty($this->errors[$this->formID]) )
		{
			foreach($this->errors[$this->formID] as $fieldName => $errorName)
			{
				$this->errorField($fieldName,$errorName);			
			}
		}
		

		$str_fields = "";
		foreach($this->_fields[$this->formID] as $field){$str_fields.= '<div style="'.$this->defaultFieldCss.'">'.$field.'</div>';}
		$this->fields = $str_fields;
		$this->set();
		return $this->form;	
	}
	
	
	protected function set()
	{
		$this->form = '<form action="'.$this->action.'" method="post" enctype="multipart/form-data">';
		$this->form.= '<input type="hidden" name="formID" value="'.$this->formID.'" />';
		$this->form.= $this->fields;
		$this->form.= '</form>';
		return $this;
	}
	
	/* fuckup */
	protected function setError($why)
	{
		exit($why);	
	}
	 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 }