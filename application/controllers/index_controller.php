<?php

if(action() == 'index')
{
	
	$item_list = array();
	for ($i=0; $i<10; $i++){	
		$item_list[] = array(
	
		 "title"=>"This is title"
		,"content"=>"Donec id elit non mi porta gravida at eget metus. Fusce dapibus, tellus ac cursus commodo, tortor mauris condimentum nibh, ut fermentum massa justo sit amet risus. Etiam porta sem malesuada magna mollis euismod. Donec sed odio dui. "
		,"image"=>"http://placehold.it/260x180"
		);	
	}

	//varexp($item_list);
}

if(action() == 'test')
{

}

