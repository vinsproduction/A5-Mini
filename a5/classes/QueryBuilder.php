<?

class QueryBuilder {
	
	protected $_select = null;
	protected $_table  = null;
	protected $_join   = null;
	protected $_innerjoin  = null;
	protected $_args   = null;
	
	private   $prefix;

	
	/*
	protected function arrayInline($array)
	{ 
		$str = '';
		for($i=0; $i < count($array);$i++)
		{
			$str.= $array[$i].( ( $i <  (count($array)-1) ) ? ',' : '');
		
		}
		return $str;			
	}
   
	*/
	function getTable($table = false, $prefix = false){
	
		if($prefix !== false){
		
			$table = $table.' '.$prefix;
			$this->prefix = $prefix;
		}

		$this->_table = $table;
		return $this;
	}
	
	function select($select = false,$prefix=false)
	{	
		if($select == false)
		{
			$this->_select = '*';
		}

		if($prefix !== false)
		{

			$array = explode(',',$select);
			
			$str = '';
			for($i=0; $i < count($array);$i++)
			{
				$str.= $prefix.'.'.trim($array[$i]).( ( $i <  (count($array)-1) ) ? ',' : '');
			
			}
			
			$select = $str;			
		
		}

		if($this->_select)  $select = $this->_select.','.$select;

		$this->_select = $select;
		
		
		return $this;
	
	}
	
	/*
		Пример запроса c плейсхолдером: ->where(array("lower(U.username)=?" => mb_strtolower($username) ));
	*/
	function where($where) {

		if(!is_array($where)) $where = array($where);
		
		if($this->_args)
		{
			$this->_args = array_merge($this->_args,$where);
		}else{
			$this->_args = $where;
		}

		return $this;
	}
	
	function join($join) {
		
		if($this->_join) 
		{		
			$this->_join = $this->_join.' LEFT JOIN '.$join;
		}else{
			$this->_join = ' LEFT JOIN '.$join;
		}
	
		return $this;
	}
	
	function innerjoin($join) {
		
		if($this->_join) 
		{		
			$this->_join = $this->_join.' INNER JOIN '.$join;
		}else{
			$this->_join = ' INNER JOIN '.$join;
		}
	
		return $this;
	}
	
	function limit($limit) {

		$this->_args["@limit"]  = $limit;	
		return $this;
	}
	
	function group($group) {
	
		$this->_args["@group"]  = $group;		
		return $this;
	}
	
	function offset($offset) {

		$this->_args["@offset"]  = $offset;		
		return $this;
	}
	
	function order($order) {

		$this->_args["@order"]  = $order;
		return $this;
	}
	
	function execute($condition = false) {

		if($condition == false)  $condition =  'db_select_all';		
		if($condition == 'row')  $condition  = 'db_select_row';
		if($condition == 'cell') $condition  = 'db_select_cell';

		$query = 'SELECT '.$this->_select.' FROM '.$this->_table.$this->_join.$this->_innerjoin;

		if($this->_args !== false)
		{
			$result =  $condition($query,$this->_args);
		}else{
			$result =  $condition($query);
		}

		$this->_table  = null;
		$this->_join   = null;
		$this->_innerjoin = null;
		$this->_select = null;
		$this->_args   = null;

		return $result;
	}
	

	/* вспомогательный метод */
	function get ($limit = false, $offset = false)
	{
		if($limit  !== false) $this->limit($limit);
		if($offset !== false) $this->offset($offset);

		$result = $this->execute();

		if(!$result) return false;

		return $result ;
	}
	
	
	
}