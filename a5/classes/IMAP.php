<?php
// Простейший класс для чтения почты по протоколу IMAP или других - для работы
// требуется подключение модуля PHP imap, умеет читать письма, преобразовывать
// HTML письмав текст, выводить информацию об имеющихся аттачах, удалять сообщения
// Все сообщения перекодирует в UTF-8
class IMAP
{
	private $mbox = null;

	function __construct($host, $login, $password)
	{
		$this->mbox = @imap_open($host, $login, $password, 0, 1);
		if ($this->mbox === false) { throw_error("IMAP: " . imap_last_error(), true); }
	}

	function get_messages_count() { return imap_num_msg($this->mbox); }

	function get_message($msgno)
	{
		$message = array();
		$message["msgno"] = $msgno;

		$header = imap_headerinfo($this->mbox, $msgno);
		$message["date"] = $header->udate;
		$message["is_deleted"] = ($header->Deleted == "D" ? true : false);

		$headers = array
		(
			"from" => @$header->sender,
			"to" => @$header->to,
			"cc" => @$header->cc,
			"bcc" => @$header->bcc,
			"reply_to" => @$header->reply_to,
			"return_path" => @$header->return_path,
		);

		foreach ($headers as $key_name => $object)
		{
			if (!is_array($object)) { continue; }
			$mailboxes = array();
			foreach ($object as $obj)
			{
				$name = isset($obj->personal) ? trim($this->decode_mime($obj->personal)) : null;
				$email = (isset($obj->mailbox) && isset($obj->host)) ? $obj->mailbox . "@" . $obj->host : null;
				$address = ($name !== null ? $name . " <" : null) . $email . ($name !== null ? ">" : null);
				$mailboxes[] = array("address" => $address, "email" => $email, "name" => $name);
			}
			$message[$key_name] = $mailboxes;
		}

		if (isset($header->in_reply_to)) { $message["in_reply_to"] = $header->in_reply_to; }
		if (isset($header->message_id)) { $message["message_id"] = $header->message_id; }
		if (isset($header->references)) { $message["references"] = $header->references; }

		if (isset($header->subject)) { $message["subject"] = $this->decode_mime($header->subject); } else { $message["subject"] = null; }
		$mime = imap_fetchstructure($this->mbox, $msgno);

		$text_parts = $this->find_text_parts($mime, $msgno);

		if (count($text_parts))
		{
			foreach ($text_parts as $text_part)
			{
				if (isset($message["text"][$text_part["type"]])) { $message["text"][$text_part["type"]] .= "\n" . $text_part["body"]; }
				else { $message["text"][$text_part["type"]] = $text_part["body"]; }
			}
		}

		if (isset($message["text"]["HTML"]))
		{
			$text_plain = HTML::to_text($message["text"]["HTML"]);
			if (!is_empty($text_plain)) { $message["text"]["PLAIN"] = $text_plain; }
		}

		$message["attachments"] = $this->find_attachments($mime);
		return $message;
	}

	function get_all_messages($is_all = false)
	{
		$mess_count = $this->get_messages_count();
		$messages = array();
		for ($msgno = 1; $msgno <= $mess_count; $msgno++)
		{
			$header = imap_headerinfo($this->mbox, $msgno);
			if ($is_all || $header->Deleted != "D") { $messages[] = $this->get_message($msgno); }
		}
		return $messages;
	}

	function delete_message($msgno) { imap_delete($this->mbox, $msgno); }
	function undelete_message($msgno) { imap_undelete($this->mbox, $msgno); }
	function expunge() { imap_expunge($this->mbox); }
	function close() { imap_close($this->mbox); }

	private function construct_text_message($part, $msgno, $index)
	{
		$message = array();
		if (is_object($part) && $part->type == 0)
		{
			if (in_array($part->subtype, array("PLAIN", "HTML")))
			{
				$charset = null;
				if ($part->ifparameters)
				{
					foreach ($part->parameters as $param)
					{ if ($param->attribute == "charset") $charset = $param->value; }
				}

				$transfer_encoding = null;
				switch ($part->encoding)
				{
					case 0: $transfer_encoding = "7BIT"; break;
					case 1: $transfer_encoding = "8BIT"; break;
					case 2: $transfer_encoding = "BINARY"; break;
					case 3: $transfer_encoding = "BASE64"; break;
					case 4: $transfer_encoding = "QUOTED-PRINTABLE"; break;
					case 5: $transfer_encoding = "OTHER"; break;
				}

				$message["type"] = $part->subtype;

				$text_body = imap_fetchbody($this->mbox, $msgno, $index);
				switch ($transfer_encoding)
				{
					case "BASE64": $text_body = imap_base64($text_body); break;
					case "QUOTED-PRINTABLE": $text_body = imap_qprint($text_body); break;
				}

				if (!$charset) { $charset = "us-ascii"; }
				$charset = $this->fix_charset($charset);
				$result = @iconv($charset, "utf-8//IGNORE//TRANSLIT", $text_body);
				if ($result !== false) { $text_body = $result; }

				$message["body"] = trim($text_body);
				return $message;
			}
		}
		return null;
	}

	private function find_text_parts($mime, $msgno, $index = null)
	{
		$text_parts = array();
		if ($mime->type == 0)
		{
			$message = $this->construct_text_message($mime, $msgno, $index ? $index : 1);
			if ($message !== null) { $text_parts[] = $message; }
		}
		// Письмо либо с вложениями, либо html письмо, либо html с вложениями
		elseif ($mime->type == 1)
		{
			// В таком варианте нужно перебрать все текстовые части
			if ($mime->subtype == "ALTERNATIVE")
			{
				for ($i = count($mime->parts) - 1; $i >= 0; $i--)
				{
					$found_part = $mime->parts[$i];
					if ($found_part->type == 0)
					{
						$message = $this->construct_text_message($found_part, $msgno, ($index !== null ? $index . "." : "") . ($i + 1));
						if ($message !== null) { $text_parts[] = $message; }
					}
					else
					{ $text_parts = array_merge($text_parts, $this->find_text_parts($found_part, $msgno, ($index !== null ? $index . "." : "") . ($i + 1))); }
				}
			}
			// В данном варианте самая первая часть должна быть либо текстовой и тогда мы её возвращаем
			// либо опять же multipart, если это так, и это первая итерация - то ищем текстовые части среди этих частей
			elseif ($mime->subtype == "MIXED" || $mime->subtype == "RELATED" || $mime->subtype == "REPORT")
			{
				if (property_exists($mime, "parts"))
				{
					foreach ($mime->parts as $i => $found_part)
					{
						if ($found_part->type == 0)
						{
							$message = $this->construct_text_message($found_part, $msgno, ($index !== null ? $index . "." : "") . ($i + 1));
							if ($message !== null) { $text_parts[] = $message; }
						}
						elseif ($found_part->type == 1)
						{ $text_parts = array_merge($text_parts, $this->find_text_parts($found_part, $msgno, ($index !== null ? $index . "." : "") . ($i + 1))); }
					}
				}
			}
		}

		return $text_parts;
	}

	private function find_attachments($mime)
	{
		$attachments = array("inline" => array(), "attachment" => array());
		if ($mime->type == 1)
		{
			if (property_exists($mime, "parts"))
			{
				foreach ($mime->parts as $part)
				{
					if ($part->type != 1)
					{
						if ($part->ifdisposition || $part->ifid)
						{
							$filename = null;

							if ($part->ifdparameters)
							{
								foreach ($part->dparameters as $param)
								{
									if ($param->attribute == "filename")
									{ $filename = $this->decode_mime($param->value); break; }
								}
							}

							if ($filename === null)
							{
								if ($part->ifparameters)
								{
									foreach ($part->parameters as $param)
									{
										if ($param->attribute == "name")
										{ $filename = $this->decode_mime($param->value); break; }
									}
								}
							}

							$attach_type = "attachment";
							if ($mime->type == 1 && $mime->subtype == "RELATED" && !$part->ifdisposition) { $attach_type = "inline"; }

							$attachments[$attach_type][] = array
							(
								"filename" => $filename,
								"size" => $part->bytes
							);
						}
					}
					else { $attachments = array_merge_recursive($attachments, $this->find_attachments($part)); }
				}
			}
		}
		return $attachments;
	}

	private function decode_mime($string)
	{
		$string_elements = imap_mime_header_decode($string);
		$string = null;
		foreach ($string_elements as $n => $element)
		{
			if (preg_match("/^\s*$/s", $element->text)) { continue; }
			if ($n && substr($element->text, 0, 1) == "\t") { $element->text = substr($element->text, 1); }
			if ($element->charset == "default") { $element->charset = "us-ascii"; }
			$element->charset = $this->fix_charset($element->charset);
			$result = @iconv($element->charset, "utf-8//IGNORE//TRANSLIT", $element->text);
			if ($result !== false) { $string .= $result; } else { $string .= $element->text; }
		}
		return $string;
	}

	private function fix_charset($charset)
	{
		$charset = strtolower($charset);
		$charset = str_replace("ks_c_5601-1987", "cp949", $charset);
		$charset = str_replace("x-euc", "euc", $charset);
		$charset = str_replace("x-windows-", "cp", $charset);
		$charset = str_replace("windows-", "cp", $charset);
		$charset = str_replace("ibm-", "cp", $charset);
		$charset = str_replace("iso-8859-8-i", "iso-8859-8", $charset);
		return $charset;
	}
}