<?php
////////////////////////////////////////////////////////////////////////
// Класс для отсылки писем, с возможностью прикрепления аттачей
// Если 8-битных символов нет, письма отсылаются в кодировке "us-ascii"
//
// Пример использования
// $mail = new MailSend();
// $mail->from("Name Lastname <mail@test.ru>");
// $mail->subject("test");
// $mail->attach("binary_content_of_file", "filename.txt", "text/plain");
// $mail->message("test");
// $mail->send("test@test.ru");
//
// Для отсылки HTML-письма вызывать нужно метод Message со вторым параметром
// $mail->message("test", true);
//
// Для вставки рисунка и дальнейшей ссылки на него в самом письме можно использовать
// $mail->attach_internal("img01", "binary_content_of_file", "img.gif", "image/gif");
// и далее вызвать
// $mail->message("<IMG src=\"cid:img01\">", true);
////////////////////////////////////////////////////////////////////////
class MailSend
{
	private $from = null;
	private $from_email = null;
	private $to = null;
	private $reply_to = null;
	private $cc = null;
	private $bcc = null;
	private $subject = null;
	private $content_type = "text/plain";
	private $message = null;
	private $message_callback = null;
	private $attachments = array();
	private $internal_attachments = array();
	private $internal_attachments_id = array();
	private $crlf = null;
	private $current_charset = "utf-8";
	private $email_regex = "[a-zA-Z0-9_]+[a-zA-Z0-9_-]*(\.[a-zA-Z0-9_][a-zA-Z0-9_-]*)*@[a-zA-Z0-9_][a-zA-Z0-9_-]*\.([a-zA-Z0-9_][a-zA-Z0-9_-]*\.)*[a-zA-Z0-9_]{2,}";
	private $send_mode = true;
	private $add_headers = array();
	private $set_headers = array();

	function __construct() { $this->use_lf(); }

	function use_lf() { $this->crlf = chr(10); }
	function use_crlf() { $this->crlf = chr(13) . chr(10); }

	// Устанавливает кодировку письма
	function set_charset($name) { return ($this->current_charset = $name); }

	// Проверяет 8-битная ли кодировка
	static function is_8bit($s) { return preg_match("~[\x80-\xff]~", $s); }

	static function safe_line($s)
	{
		$s = str_replace(chr(10), " ", $s);
		$s = str_replace(chr(13), " ", $s);
		return $s;
	}

	function to($email) { $this->to = $this->encode_mailbox_list($email); }
	function reply_to($email) { $this->reply_to = $this->encode_mailbox_list($email); }
	function cc($email) { $this->cc = $this->encode_mailbox_list($email); }
	function bcc($email) { $this->bcc = $this->encode_mailbox_list($email); }

	function clear_recipients()
	{
		$this->to = null;
		$this->cc = null;
		$this->bcc = null;
	}

	function from($email)
	{
		$email = $this->mailbox2array($email);
		if (count($email))
		{
			$email = $email[0];
			$this->from = $email["recipient"];
			$this->from_email = $email["email"];
		}
		else
		{
			$this->from = null;
			$this->from_email = null;
		}
	}

	// Формирует значение поля Subject
	function subject($subject) { $this->subject = $this->mime_encode(self::safe_line($subject)); }

	// Добавляет заголовки к письму
	function add_header($name, $content) { $this->add_headers[] = array($name, $content); }

	// Добавляет или заменяет (если такой уже есть) заголовок к письму
	function set_header($name, $content) { $this->set_headers[$name] = $content; }

	// Формирует поле Content-Type для сообщения
	function message($message, $is_html = false)
	{
		$this->message = $message;
		$this->message = str_replace("\r\n", "\n", $this->message);
		$this->message = str_replace("\n", $this->crlf, $this->message);
		$this->content_type = ($is_html ? "text/html" : "text/plain") . ";" . $this->crlf . "\t";
		if (self::is_8bit($this->message)) { $this->content_type .= "charset=\"" . $this->current_charset . "\""; }
		else { $this->content_type .= "charset=\"us-ascii\""; }
	}

	// Добавляет аттач к письму
	function attach($content, $filename = null, $type = null)
	{
		if ($filename === null) { $filename = "noname.dat"; }
		if ($type === null) { $type = "application/octet-stream"; }

		$attachment_body = null;
		$attachment_body .= "Content-Type: $type;" . $this->crlf;
		$attachment_body .= "\t" . "name=\"" . $this->mime_encode($filename) . "\"" . $this->crlf;
		$attachment_body .= "Content-Transfer-Encoding: base64" . $this->crlf;
		$attachment_body .= "Content-Disposition: attachment;" . $this->crlf;
		$attachment_body .= "\t" . "filename=\"" . $this->mime_encode($filename) . "\"" . $this->crlf . $this->crlf;
		$attachment_body .= chunk_split(base64_encode($content));
		$attachment_body .= $this->crlf;
		$this->attachments[] = $attachment_body;

		// Всегда возвращаем true, собственно в каком случае вообще у нас может быть false ?
		return true;
	}

	// Добавляет внутренний аттач с указанным ай-ди, ай-ди должны быть уникальны для каждого
	// Затем на аттач можно ссылаться в тексте письма ну например для вставки картинки в хтмл
	function attach_internal($id, $content, $filename = null, $type = null)
	{
		if ($filename === null) { $filename = "noname.dat"; }
		if ($type === null) { $type = "application/octet-stream"; }

		if (!array_key_exists($id, $this->internal_attachments_id))
		{
			$attachment_body = null;
			$attachment_body .= "Content-Type: $type;" . $this->crlf;
			$attachment_body .= "\t" . "name=\"" . $this->mime_encode($filename) . "\"" . $this->crlf;
			$attachment_body .= "Content-Transfer-Encoding: base64" . $this->crlf;
			$attachment_body .= "Content-ID: <$id>" . $this->crlf . $this->crlf;
			$attachment_body .= chunk_split(base64_encode($content));
			$attachment_body .= $this->crlf;
			$this->internal_attachments[] = $attachment_body;
			$this->internal_attachments_id[$id] = true;
		}
		// Вернём id контента
		return $id;
	}

	function clear_internal_attachments()
	{
		$this->internal_attachments = array();
		$this->internal_attachments_id = array();
	}

	function clear_attachments() { $this->attachments = array(); }

	function compile()
	{
		$headers = null;
		$content = null;
		$attboundary = "----=-next-part-" . md5(uniqid(time()));
		$altboundary = "----=-next-part-" . md5(uniqid(time()));
		$relboundary = "----=-next-part-" . md5(uniqid(time()));

		// Если в письме есть аттачменты
		if (count($this->attachments))
		{
			$headers .= "Content-Type: multipart/mixed;" . $this->crlf . "\tboundary=\"$attboundary\"";
			$content .= "This is a multi-part message in MIME format." . $this->crlf . $this->crlf;
		}
		// Если письмо в формате html с внутренними вложениями
		elseif (preg_match("~text/html~is", $this->content_type) && count($this->internal_attachments))
		{
			$headers .= "Content-Type: multipart/related;" . $this->crlf . "\tboundary=\"$relboundary\"";
			$content .= "This is a multi-part message in MIME format." . $this->crlf . $this->crlf;
		}
		// Если письмо в формате html БЕЗ внутренних вложений
		elseif (preg_match("~text/html~is", $this->content_type) && !count($this->internal_attachments))
		{
			$headers .= "Content-Type: multipart/alternative;" . $this->crlf . "\tboundary=\"$altboundary\"";
			$content .= "This is a multi-part message in MIME format." . $this->crlf . $this->crlf;
		}
		// Иначе это обычное письмо plain/text без вложений
		else
		{
			$headers .= "Content-Type: ". $this->content_type . $this->crlf;
			if (self::is_8bit($this->message)) { $headers .= "Content-Transfer-Encoding: base64"; }
			else { $headers .= "Content-Transfer-Encoding: 7bit"; }
		}

		$message = null;
		// Если письмо в формате html
		// Создаём альтернативное содержание
		if (preg_match("~text/html~is", $this->content_type))
		{
			$message .= "--$altboundary" . $this->crlf;
			if (self::is_8bit($this->message))
			{
				$message .= "Content-Type: text/plain;" . $this->crlf;
				$message .= "\t" . "charset=\"" . $this->current_charset . "\"" . $this->crlf;
				$message .= "Content-Transfer-Encoding: base64" . $this->crlf . $this->crlf;
				$message .= chunk_split(base64_encode(HTML::to_text($this->message))) . $this->crlf;
			}
			else
			{
				$message .= "Content-Type: text/plain;" . $this->crlf;
				$message .= "\t" . "charset=\"us-ascii\"" . $this->crlf;
				$message .= "Content-Transfer-Encoding: 7bit" . $this->crlf . $this->crlf;
				$message .= HTML::to_text($this->message) . $this->crlf . $this->crlf;
			}
			$message .= "--$altboundary" . $this->crlf;
			$message .= "Content-Type: ". $this->content_type . $this->crlf;
			if (self::is_8bit($this->message))
			{
				$message .= "Content-Transfer-Encoding: base64" . $this->crlf . $this->crlf;
				$message .= chunk_split(base64_encode($this->message)) . $this->crlf;
			}
			else
			{
				$message .= "Content-Transfer-Encoding: 7bit" . $this->crlf . $this->crlf;
				$message .= $this->message . $this->crlf . $this->crlf;
			}
			$message .= "--$altboundary--" . $this->crlf . $this->crlf;

			// Если письмо ещё и содержит внутренние аттачменты
			if (count($this->internal_attachments))
			{
				$message = "--$relboundary" . $this->crlf . "Content-Type: multipart/alternative;" . $this->crlf . "\tboundary=\"$altboundary\"" . $this->crlf . $this->crlf . $this->crlf . $message;

				foreach ($this->internal_attachments as $internal_attachment)
				{
					$message .= "--$relboundary" . $this->crlf;
					$message .= $internal_attachment;
				}

				$message .= "--$relboundary--" . $this->crlf . $this->crlf;
			}
		}
		// Если в обычном тексте
		else
		{
			if (self::is_8bit($this->message)) { $message .= chunk_split(base64_encode($this->message)) . $this->crlf; }
			else { $message .= $this->message . $this->crlf . $this->crlf; }
		}

		// Если в письме есть аттачменты
		if (count($this->attachments))
		{
			$content .= "--$attboundary" . $this->crlf;

			// Если наше письмо в формате html
			if (preg_match("~text/html~is", $this->content_type))
			{
				// И имеет внутренние вложения
				if (count($this->internal_attachments)) { $content .= "Content-Type: multipart/related;" . $this->crlf . "\tboundary=\"$relboundary\"" . $this->crlf . $this->crlf . $this->crlf; }
				// Если не имеет
				else { $content .= "Content-Type: multipart/alternative;" . $this->crlf . "\tboundary=\"$altboundary\"" . $this->crlf . $this->crlf . $this->crlf; }
				$content .= $message;
			}
			// Если письмо в обычном тексте
			else
			{
				$content .= "Content-Type: " . $this->content_type . $this->crlf;
				if (self::is_8bit($this->message)) { $content .= "Content-Transfer-Encoding: base64" . $this->crlf . $this->crlf; }
				else { $content .= "Content-Transfer-Encoding: 7bit" . $this->crlf . $this->crlf; }
				$content .= $message;
			}

			foreach ($this->attachments as $attachment)
			{
				$content .= "--$attboundary" . $this->crlf;
				$content .= $attachment;
			}

			$content .= "--$attboundary--" . $this->crlf . $this->crlf;
		}
		// Если в письме нет аттачментов
		else { $content .= $message; }

		return array($headers, $content);
	}

	// Режимы работы
	// true, 1 - стандартный (по-умолчанию)
	// 0, false, null - никакие письма реально не рассылаются
	// другое - все письма реально будут высылаться на данный е-мэил
	function mode($mode) { $this->send_mode = $mode; }

	function send($to = null)
	{
		if (!$this->send_mode) { return true; }
		if ($to === null) { $to = $this->to; } else { $to = $this->encode_mailbox_list($to); }
		if ($to !== null)
		{
			$subject = $this->subject;

			$headers = array();
			if ($this->from !== null) { $headers[] = array("From", $this->from); }
			if ($this->reply_to !== null) { $headers[] = array("Reply-To", $this->reply_to); }
			if ($this->cc !== null) { $headers[] = array("Cc", $this->cc); }
			if ($this->bcc !== null) { $headers[] = array("Bcc", $this->bcc); }

			$headers[] = array("Message-ID", "<" . getmypid() . "." . md5(microtime(true)) . "@" . php_uname("n") . ">");
			$headers[] = array("MIME-Version", "1.0");
			$headers[] = array("Date", date("r"));
			$headers[] = array("X-Mailer", "A5 MailSend Class 3.07");

			// Добавляем другие заголовки если есть
			$headers = array_merge($headers, $this->add_headers);

			// Удаляем все заголовки - которые решили переопределить
			foreach ($this->set_headers as $name => $value)
			{
				foreach ($headers as $i => $header)
				{ if (strtolower($name) == strtolower($header[0])) unset($headers[$i]); }
			}

			foreach ($this->set_headers as $name => $value) { $headers[] = array($name, $value); }

			// Если установлен режим высылки на определённый e-mail - изменяем
			// получателей и удаляем заголовки копий и скрытых копий
			if ($this->send_mode != 1 && $this->send_mode !== true)
			{
				$to = $this->encode_mailbox_list($this->send_mode);
				foreach ($headers as $i => $header) { if (in_array(strtolower($header[0]), array("cc", "bcc"))) unset($headers[$i]); }
			}

			$headers_content = null;
			foreach ($headers as $header) { $headers_content .= $header[0] . ": " . $header[1] . $this->crlf; }
			$headers = $headers_content;
			unset($headers_content);

			list($header, $content) = $this->compile();
			$headers .= $header;

			$status = @mail($to, $subject, $content, $headers, $this->from_email !== null ? "-f" . $this->from_email : "");
			if ($status === false) { $GLOBALS["php_errormsg"] = @$php_errormsg; }

			// После успешной отсылки письма - сбрасываем все поля получаетелей, во избежании
			// высылки других писем нежелательным получателям, такое может происходить
			// если используется одна инстанция класса для множественной рассылки
			$this->clear_recipients();

			return $status;
		}
	}

	private function mime_encode($text, $charset = null)
	{
		if ($charset === null) { $charset = $this->current_charset; }
		if (self::is_8bit($text)) { return "=?" . $charset . "?B?" . base64_encode($text) . "?="; } else { return $text; }
	}

	// Метод принимает на вход массив или строку со списком e-mail и возможно имён получателей
	// на выход возвращает такую же строку, но закодированную по всем правилам почтовых сообщений
	private function encode_mailbox_list($email)
	{
		if (is_array($email)) { $email = implode(", ", $email); }
		$mailbox_list = $this->mailbox2array($email);
		$mailbox_string = null;
		if ($mailbox_list)
		{
			foreach ($mailbox_list as $mail)
			{
				if (strlen($mailbox_string)) { $mailbox_string .= ", "; }
				$mailbox_string .= $mail["recipient"];
			}
		}
		return $mailbox_string;
	}

	// Метод принимает на вход обычную строку списка получателей и выдаёт на выходе массив,
	// каждый элемент которого является массивом с двумя ключами: recipient и email
	// recipient - содержит в себе полное представление получателя (включая его имя и е-мэил)
	//             закодированное при этом mime_encode (если указано имя)
	// email - содержит в себе только чистый e-mail, исключая любой другой мусор
	private function mailbox2array($email)
	{
		$email = trim(self::safe_line($email));
		$mailbox_list = array();
		while (1)
		{
			$mailbox = array();

			if (preg_match("/^ ({$this->email_regex}) \s* (?:,+ \s* | $) /sx", $email, $regs))
			{ $mailbox = array("", $regs[1]); }
			elseif (preg_match("/^ (.+?) \s* <({$this->email_regex})> \s* (?:,+ \s* | $) /sx", $email, $regs))
			{
				$regs[1] = trim($regs[1]);
				if (!preg_match("/^\".*\"$/", $regs[1]) && preg_match("/[()<>@,;:\\\".[\]]/", $regs[1])) { $regs[1] = '"' . $regs[1] . '"'; }
				$mailbox = array(trim($regs[1]), $regs[2]);
			}
			else { break; }

			$email = substr($email, strlen($regs[0]));
			if (strlen($mailbox[0])) { $mailbox[0] = $this->mime_encode($mailbox[0]); }
			if (strlen($mailbox[0])) { $mailbox["recipient"] = $mailbox[0] . " <" . $mailbox[1] . ">"; } else { $mailbox["recipient"] = $mailbox[1]; }

			$mailbox["email"] = $mailbox[1];
			unset($mailbox[0]); unset($mailbox[1]);

			$mailbox_list[] = $mailbox;
		}
		return $mailbox_list;
	}
}