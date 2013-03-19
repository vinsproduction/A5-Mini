<?php
class Cache_APC
{
	function __construct($params = array()) { return true; }
	
	function store($cache_id, $data, $time = 0, $tags = array())
	{
		$data = array("data" => $data, "tags" => array());
		$tag_time = (string) microtime(true);
		foreach ($tags as $tag_id)
		{
			$data["tags"][$tag_id] = $tag_time;
			if (false === apc_fetch($tag_id)) { apc_store($tag_id, $tag_time); }
		}
		return apc_store($cache_id, serialize($data), $time) ? true : false;
	}
	
	function fetch($cache_id)
	{
		$data = null;
		if (false !== $data = apc_fetch($cache_id))
		{
			$data = @unserialize($data);
			if ($data !== false)
			{
				foreach ($data["tags"] as $tag_id => $tag_check_time)
				{
					$tag_time = apc_fetch($tag_id);
					if ($tag_time === false) { $data["data"] = null; break; }
					elseif ($tag_time > $tag_check_time) { $data["data"] = null; break; }
				}
				if ($data["data"] === null) { $this->delete($cache_id); }
				return $data["data"];
			}
		}
		return null;
	}
	
	function delete($cache_id)
	{
		apc_delete($cache_id);
		return true;
	}

	function delete_tags($tags)
	{
		foreach ($tags as $tag_id) { $this->delete($tag_id); }
		return true;
	}
}