<?php
class Cache_Memcached
{
	private $mc = null;
	
	function __construct($host, $port = 11211)
	{
		if (class_exists("Memcached")) { $this->mc = new Memcached(md5(__FILE__)); } else { $this->mc = new Memcache(); }
		if (is_array($host)) { $servers = $host; } else { $servers = array(array($host, $port)); }
		$this->add_servers($servers);
	}
	
	function store($cache_id, $data, $time = 0, $tags = array())
	{
		$data = array("data" => $data, "tags" => array());
		$tags_new = array();
		$tags_stored = $this->get_multi($tags);
		$tag_time = (string) microtime(true);
		foreach ($tags as $tag_id)
		{
			$data["tags"][$tag_id] = $tag_time;
			if (!array_key_exists($tag_id, $tags_stored)) { $tags_new[$tag_id] = $tag_time; }
		}
		$this->set_multi($tags_new);
		return $this->set($cache_id, serialize($data), $time) ? true : false;
	}
	
	function fetch($cache_id)
	{
		$data = null;
		if (false !== $data = $this->mc->get($cache_id))
		{
			$data = @unserialize($data);
			if ($data !== false)
			{
				$tags_stored = $this->get_multi(array_keys($data["tags"]));
				foreach ($data["tags"] as $tag_id => $tag_check_time)
				{
					if (!array_key_exists($tag_id, $tags_stored) || $tags_stored[$tag_id] > $tag_check_time)
					{ $data["data"] = null; break; }
				}
				if ($data["data"] === null) { $this->delete($cache_id); }
				return $data["data"];
			}
		}
		return null;
	}
	
	function delete($cache_id)
	{
		$this->mc->delete($cache_id);
		return true;
	}

	function delete_tags($tags)
	{
		foreach ($tags as $tag_id) { $this->delete($tag_id); }
		return true;
	}
	
	private function add_servers($servers)
	{
		if ($this->mc instanceof Memcached) { return $this->mc->addServers($servers); }
		else
		{
			$result = null;
			foreach ($servers as $server)
			{
				if (!isset($server[0])) { $server[0] = "localhost"; }
				if (!isset($server[1])) { $server[1] = 11211; }
				$result = $this->mc->addServer($server[0], @$server[1], true);
			}
			return $result;
		}
	}

	private function set($key, $data, $ttl = 0)
	{
		if ($this->mc instanceof Memcached) { return $this->mc->set($key, $data, $ttl); }
		else { return $this->mc->set($key, $data, 0, $ttl); }
	}

	private function get_multi($keys)
	{
		if ($this->mc instanceof Memcached) { return $this->mc->getMulti($keys); }
		else
		{
			$items = array();
			foreach ($keys as $key)
			{
				$data = $this->mc->get($key);
				if ($data !== false) { $items[$key] = $data; }
			}
			return $items;
		}
	}
	
	private function set_multi($items, $time = 0)
	{
		if ($this->mc instanceof Memcached) { return $this->mc->setMulti($items, $time); }
		else
		{
			$result = true;
			foreach ($items as $key => $value) { $result = $this->set($key, $value, $time); }
			return $result;
		}
	}
}