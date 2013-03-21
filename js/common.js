
/* Всякие нестандартные хелперы на основе библиотек объявленных выше в скриптах */



/* /js/jquery.cookie.js */

var Cookie = {

	set : function(name, val){
		$.cookie(name, val);
		console.log('cookie is set');
	},
	get	: function(name){				
		return $.cookie(name);
	},
	destroy : function(name){

		$.cookie(name, null);
		console.log('cookie has been destroyed');
	}	
} 



/* /bootstrap/js/bootstrap.js */

var	Popup = {
	show: function(id, callback){

		if(callback) { callback(); } else { $('#'+id).modal('show'); }				
	},
	hide: function(){
		$('.modal').modal('hide');
	}
}


/* addthis.com */

var addthis_config = {
   ui_language : 'en',
}