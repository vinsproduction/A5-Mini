
	
	/* ############### HELPERS Javascript + jQuery ############### */
	

	function disableConsole()
	{
		if(!window.console) window.console = {};		
		var methods = ["log", "debug", "warn", "info"];
		for(var i=0;i<methods.length;i++){
			console[methods[i]] = function(){};
		}		
	}
	

	
	/* INCLUDE JAVASCRIPT FILE
	EXAMPLE IncludeJavaScript('/js/helpers.js'); */
	function IncludeJavaScript(jsFile) { document.write('<script type="text/javascript" src="' + jsFile + '"></scr' + 'ipt>'); }

	/* BROWSER */
	if (!window._ua) { var _ua = navigator.userAgent.toLowerCase(); }

	var browser = {
	  version: (_ua.match( /.+(?:me|ox|on|rv|it|era|ie)[\/: ]([\d.]+)/ ) || [0,'0'])[1],
	  opera: /opera/i.test(_ua),
	  msie: (/msie/i.test(_ua) && !/opera/i.test(_ua)),
	  msie6: (/msie 6/i.test(_ua) && !/opera/i.test(_ua)),
	  msie7: (/msie 7/i.test(_ua) && !/opera/i.test(_ua)),
	  msie8: (/msie 8/i.test(_ua) && !/opera/i.test(_ua)),
	  msie9: (/msie 9/i.test(_ua) && !/opera/i.test(_ua)),
	  mozilla: /firefox/i.test(_ua),
	  chrome: /chrome/i.test(_ua),
	  safari: (!(/chrome/i.test(_ua)) && /webkit|safari|khtml/i.test(_ua)),
	  iphone: /iphone/i.test(_ua),
	  ipod: /ipod/i.test(_ua),
	  iphone4: /iphone.*OS 4/i.test(_ua),
	  ipod4: /ipod.*OS 4/i.test(_ua),
	  ipad: /ipad/i.test(_ua),
	  android: /android/i.test(_ua),
	  bada: /bada/i.test(_ua),
	  mobile: /iphone|ipod|ipad|opera mini|opera mobi|iemobile/i.test(_ua),
	  msie_mobile: /iemobile/i.test(_ua),
	  safari_mobile: /iphone|ipod|ipad/i.test(_ua),
	  opera_mobile: /opera mini|opera mobi/i.test(_ua),
	  mac: /mac/i.test(_ua)
	};
	

	function getBrowser(){
		for(var i in browser){
			if(browser[i] == true) { return i + ' ver.'+ browser.version; }
		}		
		return 'undefined browser';
	}


	function trim(text) { return (text || '').replace(/^\s+|\s+$/g, ''); }
	function nl2br(str) { return str.replace(/([^>])\n/g, '$1<br/>'); }
	
	function rand(mi, ma) { return Math.random() * (ma - mi + 1) + mi; }
	function irand(mi, ma) { return Math.floor(rand(mi, ma)); }
	
	function isFunction(obj) {return Object.prototype.toString.call(obj) === '[object Function]'; }
	function isString(obj) { return Object.prototype.toString.call(obj) === '[object String]'; }
	function isArray(obj) { return Object.prototype.toString.call(obj) === '[object Array]'; }
	function isObject(obj) { return Object.prototype.toString.call(obj) === '[object Object]'; }
	
	function isEmpty(o){
		if( isString(o) ) {return (trim(o) ===  "") ? true : false;}
		if( isArray(o) )  {return (o.length === 0) ? true : false;}
		if( isObject(o) ) {for(var i in o){ if(o.hasOwnProperty(i)){return false;} } return true; }
	}
	function stripHTML(text) { return text ? text.replace(/<(?:.|\s)*?>/g, '') : ''; }
	function escapeHTML(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
	function escapeRE(s) { return s ? s.replace(/([.*+?^${}()|[\]\/\\])/g, '\\$1') : ''; }
	
	function convertPixelsToPoints(pixels) { return parseInt(pixels) * 72 / 96; } 
	
	function intval(value) {
	  if (value === true) return 1;
	  return parseInt(value) || 0;
	}
	function floatval(value) {
	  if (value === true) return 1;
	  return parseFloat(value) || 0;
	}

	function winToUtf(text) {
	  var m, i, j, code;
	  m = text.match(/&#[0-9]{2}[0-9]*;/gi);
	  for (j in m) {
		var v = '' + m[j]; // buggy IE6
		code = intval(v.substr(2, v.length - 3));
		if (code >= 32 && ('&#' + code + ';' == v)) { // buggy IE6
		  text = text.replace(v, String.fromCharCode(code));
		}
	  }
	  text = text.replace(/&quot;/gi, '"').replace(/&amp;/gi, '&').replace(/&lt;/gi, '<').replace(/&gt;/gi, '>');
	  return text;
	}
	
	/* Склонение числительных 
	   declOfNum(5, ['секунда', 'секунды', 'секунд']) 
	*/
	function declOfNum(number, titles)  
	{  
		cases = [2, 0, 1, 1, 1, 2];  
		return titles[ (number%100>4 && number%100<20)? 2 : cases[(number%10<5)?number%10:5] ];  
	} 
	
	
	
	/*  Авто перенос строк в зависимости от ширины слоя
		Example: nl2br( wordwrap('петя коля вася', 5, '\n' ) )
	*/
	function wordwrap( str, int_width, str_break, cut ) {
		var i, j, s, r = str.split("\n");
		if(int_width > 0) for(i in r){
			for(s = r[i], r[i] = ""; s.length > int_width;
				j = cut ? int_width : (j = s.substr(0, int_width).match(/\S*$/)).input.length - j[0].length || int_width,
				r[i] += s.substr(0, j) + ((s = s.substr(j)).length ? str_break : "")
			);
			r[i] += s;
		}
		return r.join("\n");				
	}
	
	
	/* Функци вытаскивающая имя файла картинки из системного патча, типа C:\fakepath\long_distance_call.jpg 
	   Вернет long_distance_call.jpg 
	*/
	function getImgNameFrom(fullPath){
			
		var startIndex = (fullPath.indexOf('\\') >= 0 ? fullPath.lastIndexOf('\\') : fullPath.lastIndexOf('/'));
		var filename = fullPath.substring(startIndex);
		if (filename.indexOf('\\') === 0 || filename.indexOf('/') === 0) {
				filename = filename.substring(1);
		}
		
		var file = filename.split('.');				
		var allowedExt = new Array('jpg','jpeg','gif','png');

		return inArray(file[1], allowedExt) ? filename : false;		

		
	}
	

	/* EXAMPLE
	   getDate('d/m-y h:i') 
	   result: 28/03-2012 2:26
	*/
	function getDate(format)
	{	

		// d - день
		// j - день без нуля
		// m - месяц
		// n - месяц без нуля
		// M - месяц (название)
		// y - год
		// H - часы
		// h - часы без нуля
		// i - минуты
		// s - секунды

		var currentTime = new Date();
		var month 	= currentTime.getMonth() + 1;
		var day 	= currentTime.getDate();
		var year 	= currentTime.getFullYear();
		var hours 	= currentTime.getHours();
		var minutes = currentTime.getMinutes();
		var seconds = currentTime.getSeconds();
		
		var monthName = new Array ("января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря");
		
		var dayZ 	 = (day < 10) 		? "0" + day : day;
		var minutesZ = (minutes < 10) 	? "0" + minutes : minutes;
		var secondsZ = (seconds < 10) 	? "0" + seconds : seconds;
		var monthZ 	 = (month < 10) 	? "0" + month : month;
		var hoursZ 	 = (hours < 10) 	? "0" + hours : hours;
		
		return (format) 
				? format
					.replace('d',dayZ)
					.replace('j',day)
					.replace('m',monthZ)
					.replace('n',month)
					.replace('M',monthName[month-1] )
					.replace('t',year)
					.replace('h',hours)
					.replace('H',hoursZ)
					.replace('i',minutes)
					.replace('s',seconds)
				: dayZ +'-'+ monthZ +'-'+ year +' '+ hoursZ +':'+ minutesZ+':'+secondsZ ;
	}


	/* ############### Arrays, objects ############### */

	/* EXAMPLE
	var a = {"q":5};
	each(a, function(k,v){ a[k] = v+1 } ) //result: Object {q: 6}
	*/
	function each(object, callback) {
	  var name, i = 0, length = object.length;

	  if (length === undefined) {
		for (name in object)
		  if (callback.call(object[name], name, object[name]) === false)
			break;
	  } else {
		for (var value = object[0];
		  i < length && callback.call(value, i, value) !== false;
			value = object[++i]) {}
	  }

	  return object;
	}

	/* EXAMPLE: indexOf(Array('test'),'test') result: 0 */
	
	function indexOf(arr, value, from) {
	  for (var i = from || 0, l = arr.length; i < l; i++) {
		if (arr[i] == value) return i;
	  }
	  return -1;
	}

	/* EXAMPLE: inArray('test',Array('test')) result: true */
	function inArray(value, arr) {
	  return indexOf(arr, value) != -1;
	}
	/* EXAMPLE 
	var a = {'y':'s'}
	t = clone(a,t)
	result: object t {y: "s"}
	*/
	function clone(obj, req) {
	  var newObj = isArray(obj) ? [] : {};
	  for (var i in obj) {
		if (req && typeof(obj[i]) === 'object' && i !== 'prototype') {
		  newObj[i] = clone(obj[i]);
		} else {
		  newObj[i] = obj[i];
		}

	  }
	  return newObj;
	}

	/* EXAMPLE. Разница по ключам
	var t = Array('y')
	var a = Array('a')
	arrayKeyDiff(t,a)
	result Object {0: "y"}
	*/
	function arrayKeyDiff(a) { 
	  var arr_dif = {}, i = 1, argc = arguments.length, argv = arguments, key, found;
	  for (key in a){
		found = false;
		for (i = 1; i < argc; i++){
		  if (argv[i][key] && (argv[i][key] == a[key])){
			found = true;
		  }
		}
		if (!found) {
		  arr_dif[key] = a[key];
		}
	  }
	  return arr_dif;
	}
	
	/* Create an array containing the range of integers or characters from low to high (inclusive)	*/
	function range (low, high, step) {
		// http://kevin.vanzonneveld.net
		// +   original by: Waldo Malqui Silva
		// *     example 1: range ( 0, 12 );
		// *     returns 1: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
		// *     example 2: range( 0, 100, 10 );
		// *     returns 2: [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
		// *     example 3: range( 'a', 'i' );
		// *     returns 3: ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i']
		// *     example 4: range( 'c', 'a' );
		// *     returns 4: ['c', 'b', 'a']
		var matrix = [];
		var inival, endval, plus;
		var walker = step || 1;
		var chars = false;

		if (!isNaN(low) && !isNaN(high)) {
			inival = low;
			endval = high;
		} else if (isNaN(low) && isNaN(high)) {
			chars = true;
			inival = low.charCodeAt(0);
			endval = high.charCodeAt(0);
		} else {
			inival = (isNaN(low) ? 0 : low);
			endval = (isNaN(high) ? 0 : high);
		}

		plus = ((inival > endval) ? false : true);
		if (plus) {
			while (inival <= endval) {
				matrix.push(((chars) ? String.fromCharCode(inival) : inival));
				inival += walker;
			}
		} else {
			while (inival >= endval) {
				matrix.push(((chars) ? String.fromCharCode(inival) : inival));
				inival -= walker;
			}
		}

		return matrix;
	}
	
	
	/* ############### jQuery! ############### */

	
	/* AJAX POST */
	var throbberSRC = '/img/throbber.gif';
	var throbberRoundSRC = '/img/throbber_round.gif';
	var throbberIMG = '<img src="'+throbberSRC+'" />';
	var throbberRoundIMG = '<img src="'+throbberRoundSRC+'" />';
	function throbberON(el) { el.html(throbberIMG); }
	function throbberONcenter(el) { el.html('<div style="text-align: center">'+throbberIMG+'</div>'); }
	
	function throbberOFF(el){ el.html(''); }

	function ajaxPost(url,params,callback,throbber) {

		var params = params || {}

		console.log( '===>\nAJAX REQUEST\nURL:'+url+'\nPARAMS: '+ JSON.stringify(params)+'\n===>');
		
		$.ajax({
				 type: 'POST'
				//,timeout: 5000 // MAX время ожидание ответа в мс, превышение - fail
				,url:  url
				,data: params
				,beforeSend: function(xhr) { if(throbber) throbberON(throbber); } 
		})				
		.done(function(data) {

			var json; // check type - json or string
			try { json = $.parseJSON(data); } catch (e) { json = false; }

			data = (json) ? json : data; 
			
			if(callback) callback(data);
			if(throbber) throbberOFF(throbber);
			
			data  = JSON.stringify(data);
			
			console.log( '<===\nAJAX RESPONSE\nURL:'+url+'\nDATA: '+data+'\n<===' );
			
		})
		.fail(function(jqXHR, statusText, errorThrown) {
			
			console.log('Ajax FAIL:'+url+'\nPost: '+ JSON.stringify(params));
			console.log('statusText: ' + statusText); 
			console.log('errorThrown: '+ errorThrown); 
			console.log('jqXHR: ' + jqXHR.statusText); 
		})
		.always(function() { });

	};

	/* Получение выделенного текста на странице */
	function getSelText()
	{
	    var txt = '';
	    if(window.getSelection)
	    {
	        txt = window.getSelection();
	    }
	    else if (document.getSelection)
	    {
	        txt = document.getSelection();
	    }
	    else if (document.selection)
	    {
	        txt = document.selection.createRange().text;
	    }
	    else return;
		return txt.toString();
	}
	
	
	
	/*  Функция оборачивания тегами выделенной области в textarea 
		Example: $('textarea').wrapSelected("[b]", "[/b]");
	*/
	
	(function($) {
	  $.fn.wrapSelected = function(open, close) {
		return this.each(function() {
		  var textarea = $(this);
		  var value = textarea.val();
		  var start = textarea[0].selectionStart;
		  var end = textarea[0].selectionEnd;
		  textarea.val(
			value.substr(0, start) + 
			open + value.substring(start, end) + close + 
			value.substring(end, value.length)
		  );
		});
	  };
	})(jQuery);
	
	
	/* Функция поиска позиции курсора в textarea
	
	$("input:text.sensor").keydown(function(){
			$("span.caretObject").text($(this).caret());
		}).keypress(function(){
			$("span.caretObject").text($(this).caret());
		}).mousemove(function(){
			$("span.caretObject").text($(this).caret());
		});
	 *
	 * Copyright (c) 2010 C. F., Wong (<a href="http://cloudgen.w0ng.hk">Cloudgen Examplet Store</a>)
	 * Licensed under the MIT License:
	 * http://www.opensource.org/licenses/mit-license.php
	 * 
	 */
	 (function($,len,createRange,duplicate){
		$.fn.caret=function(options,opt2){
			var start,end,t=this[0],browser=$.browser.msie;
			if(typeof options==="object" && typeof options.start==="number" && typeof options.end==="number") {
				start=options.start;
				end=options.end;
			} else if(typeof options==="number" && typeof opt2==="number"){
				start=options;
				end=opt2;
			} else if(typeof options==="string"){
				if((start=t.value.indexOf(options))>-1) end=start+options[len];
				else start=null;
			} else if(Object.prototype.toString.call(options)==="[object RegExp]"){
				var re=options.exec(t.value);
				if(re != null) {
					start=re.index;
					end=start+re[0][len];
				}
			}
			if(typeof start!="undefined"){
				if(browser){
					var selRange = this[0].createTextRange();
					selRange.collapse(true);
					selRange.moveStart('character', start);
					selRange.moveEnd('character', end-start);
					selRange.select();
				} else {
					this[0].selectionStart=start;
					this[0].selectionEnd=end;
				}
				this[0].focus();
				return this
			} else {
			   if(browser){
					var selection=document.selection;
					if (this[0].tagName.toLowerCase() != "textarea") {
						var val = this.val(),
						range = selection[createRange]()[duplicate]();
						range.moveEnd("character", val[len]);
						var s = (range.text == "" ? val[len]:val.lastIndexOf(range.text));
						range = selection[createRange]()[duplicate]();
						range.moveStart("character", -val[len]);
						var e = range.text[len];
					} else {
						var range = selection[createRange](),
						stored_range = range[duplicate]();
						stored_range.moveToElementText(this[0]);
						stored_range.setEndPoint('EndToEnd', range);
						var s = stored_range.text[len] - range.text[len],
						e = s + range.text[len]
					}
				} else {
					var s=t.selectionStart,
						e=t.selectionEnd;
				}
				
				console.log(s);
				
				
				var te=t.value.substring(s,e);
				return {start:s,end:e,text:te,replace:function(st){
					return t.value.substring(0,s)+st+t.value.substring(e,t.value[len])
				}}
			}
		}
	})(jQuery,"length","createRange","duplicate");
	
	
	












