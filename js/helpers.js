/* ############### HELPERS + jQuery ############### */

var helpers_is_good = {}, // ORIGINAL NAMESPACE
	$$;


(function($){
	
	$$ = helpers_is_good; // SHORT NAMESPACE
	
	if(!window.console) { 	
		window.console = {
			log: 	function(){},
			debug: 	function(){},
			warn: 	function(){},
			info: 	function(){}
		}	
	}

	$.disableConsole = function(){
		var i;
		for(i in window.console ){
			window.console[i] = function(){};
		}		
	}
		
	$.log = function(obj) { 
		console.log(obj);
	}
		
	$.jlog = function(obj) { 

		if( !window.JSON  || !window.JSON.hasOwnProperty('stringify') ) {
			window.JSON = { stringify: function(){} };
		}			
		return window.JSON.stringify(obj); 
	}
	
	
	/* INCLUDE JAVASCRIPT FILE
	EXAMPLE IncludeJavaScript('/js/helpers.js'); */
	$.includeJavaScript = function(jsFile) { document.write('<script type="text/javascript" src="' + jsFile + '"></scr' + 'ipt>'); }

	/* BROWSER */
	
	$.userAgent = navigator.userAgent.toLowerCase();

	$.browser = {
	  version: ($.userAgent.match( /.+(?:me|ox|on|rv|it|era|ie)[\/: ]([\d.]+)/ ) || [0,'0'])[1],
	  opera: /opera/i.test($.userAgent),
	  msie: (/msie/i.test($.userAgent) && !/opera/i.test($.userAgent)),
	  msie6: (/msie 6/i.test($.userAgent) && !/opera/i.test($.userAgent)),
	  msie7: (/msie 7/i.test($.userAgent) && !/opera/i.test($.userAgent)),
	  msie8: (/msie 8/i.test($.userAgent) && !/opera/i.test($.userAgent)),
	  msie9: (/msie 9/i.test($.userAgent) && !/opera/i.test($.userAgent)),
	  mozilla: /firefox/i.test($.userAgent),
	  chrome: /chrome/i.test($.userAgent),
	  safari: (!(/chrome/i.test($.userAgent)) && /webkit|safari|khtml/i.test($.userAgent)),
	  iphone: /iphone/i.test($.userAgent),
	  ipod: /ipod/i.test($.userAgent),
	  iphone4: /iphone.*OS 4/i.test($.userAgent),
	  ipod4: /ipod.*OS 4/i.test($.userAgent),
	  ipad: /ipad/i.test($.userAgent),
	  android: /android/i.test($.userAgent),
	  bada: /bada/i.test($.userAgent),
	  mobile: /iphone|ipod|ipad|opera mini|opera mobi|iemobile/i.test($.userAgent),
	  msie_mobile: /iemobile/i.test($.userAgent),
	  safari_mobile: /iphone|ipod|ipad/i.test($.userAgent),
	  opera_mobile: /opera mini|opera mobi/i.test($.userAgent),
	  mac: /mac/i.test($.userAgent)
	};
	

	$.trim = function (text) { return (text || '').replace(/^\s+|\s+$/g, ''); }
	$.nl2br = function(str) { return str.replace(/([^>])\n/g, '$1<br/>'); }
	
	$.rand = function(mi, ma) { return Math.random() * (ma - mi + 1) + mi; }
	$.irand = function(mi, ma) { return Math.floor(rand(mi, ma)); }
	
	$.isFunction = function(obj) {return Object.prototype.toString.call(obj) === '[object Function]'; }
	$.isString = function(obj) { return Object.prototype.toString.call(obj) === '[object String]'; }
	$.isArray = function(obj) { return Object.prototype.toString.call(obj) === '[object Array]'; }
	$.isObject = function(obj) { return Object.prototype.toString.call(obj) === '[object Object]'; }
	
	$.isEmpty = function(o){
		if( isString(o) ) {return (trim(o) ===  "") ? true : false;}
		if( isArray(o) )  {return (o.length === 0) ? true : false;}
		if( isObject(o) ) {for(var i in o){ if(o.hasOwnProperty(i)){return false;} } return true; }
	}
	$.stripHTML = function(text) { return text ? text.replace(/<(?:.|\s)*?>/g, '') : ''; }
	$.escapeHTML = function(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
	$.escapeRE = function(s) { return s ? s.replace(/([.*+?^${}()|[\]\/\\])/g, '\\$1') : ''; }
	
	$.convertPixelsToPoints = function(pixels) { return parseInt(pixels) * 72 / 96; } 
	
	$.intval = function(value) {
	  if (value === true) return 1;
	  return parseInt(value) || 0;
	}
	$.floatval = function(value) {
	  if (value === true) return 1;
	  return parseFloat(value) || 0;
	}

	$.winToUtf = function(text) {
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
	$.declOfNum = function(number, titles){  
		var cases = [2, 0, 1, 1, 1, 2];  
		return titles[ (number%100>4 && number%100<20)? 2 : cases[(number%10<5)?number%10:5] ];  
	} 

	
	/*  Авто перенос строк в зависимости от ширины слоя
		Example: nl2br( wordwrap('петя коля вася', 5, '\n' ) )
	*/
	$.wordwrap = function( str, int_width, str_break, cut ) {
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
	

	/* EXAMPLE
	   getDate('d/m-y h:i') 
	   result: 28/03-2012 2:26
	*/
	$.date = function(format){	

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
	$.each = function(object, callback) {
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
	
	$.indexOf = function(arr, value, from) {
	  for (var i = from || 0, l = arr.length; i < l; i++) {
		if (arr[i] == value) return i;
	  }
	  return -1;
	}

	/* EXAMPLE: inArray('test',Array('test')) result: true */
	$.inArray = function(value, arr) {
	  return $.indexOf(arr, value) != -1;
	}


	/* EXAMPLE. Разница по ключам
	var t = Array('y')
	var a = Array('a')
	arrayKeyDiff(t,a)
	result Object {0: "y"}
	*/
	$.arrayKeyDiff = function(a) { 
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
	$.range  = function(low, high, step) {
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
	
	if( !jQuery ) { $.includeJavaScript('http://code.jquery.com/jquery-1.9.1.min.js') }
	
	$.ajax = function(url,params,callback, before,done,fail,always) {
	
		var params = ( !params || !$.isObject(params) ) ?  {} :  params;

		$.log( "\n===>\nAjax: '"+url+"'\nRequest: "+ $.jlog(params)+"\n===>");
		
		jQuery.ajax({
				 type: 'POST'
				//,timeout: 5000 // MAX время ожидание ответа в мс, превышение - fail
				,url:  url
				,data: params
				,beforeSend: function(xhr) { if(before) before(); } 
		})				
		.done(function(data) {

			var json; // check type json
			try { json = jQuery.parseJSON(data); } catch (e) { json = false; }

			data = (json) ? json : data; 
	
			$.log( "\n<===\nAjax: '"+url+"'\nResponse: "+$.jlog(data)+"\n<===" );
			
			if(callback) callback(data);
			if(done) done();

		})
		.fail(function(jqXHR, statusText, errorThrown) {
			
			$.log(
				'\n<===\nAjax: '+url+'\nRequest: '+ $.jlog(params) + '\n' +
				'statusText: ' + statusText	+ '\n' + 
				'errorThrown: '+ errorThrown + '\n' +
				'jqXHR: ' + jqXHR.statusText + '\n' +
				'<==='
			);
		
			if(fail) fail();
		})
		.always(function() {
			if(always) always();
		});

	}

	/* PLACEHOLDER FOR FORM INPUTS  
		use: $$.placeholder($('input[name="name"]'),'Имя');
	*/

	$.placeholder = function(element,value){
	    element.focus(function(){
	        if(element.val() == value) element.val('');         
	    }).
	    blur(function(){
	        if(element.val() == '') element.val(value);
	    });

	    element.blur();
	}


	
})(helpers_is_good);


	
	








