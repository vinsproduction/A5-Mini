/*	EXAMPLE!
	
	<? if($articleCommentsCount > $limit) :?>
	<div id="pager_comments">
		<? for($i=1; $i<=$p_count;$i++)  :?>
			<a href="#"><?= $i ?></a>
		<? endfor;?>	
	</div>		
	<? endif;?>
	
	<aside class="comments" id="Comments_JS">
		<h2>Комментарии (<font class="countBar_JS"><?= $articleCommentsCount ?></font>)</h2>
		
		<article class="commentExamle_JS" style="display:none">
			<div class="fromUserID_JS" style="display:none"></div>
			<strong class="fromUserName_JS"></strong>
			<img class="avatar_JS" src="/img/avatars/50x50.png" alt="" />
			<p><img src="/img/comment.png" alt="" /><font class="content_JS"></font></p>
			<span class="created_JS"></span>
			<div><a class="remove_JS" href="#">Удалить</a><a class="answer_JS" href="#">Ответить</a><a class="answerPrivate_JS" href="#">Написать личное сообщение</a></div>
		</article>
		
		<div class="list_JS">
		<? if($articleCommentsList) :?><? foreach($articleCommentsList as $item)  :?>
			<? if(  !$item['private']  || ( $item['from_user'] == $authUserID ||  $item['to_user'] == $authUserID ) ) :?>
			<article class="commentItem_JS" id="comment_<?= $item['id']; ?>">
				<div class="fromUserID_JS" style="display:none"><?= $item['from_user']; ?></div>
				<strong class="fromUserName_JS"><?= $item['username'] ?></strong>
				<img class="avatar_JS" src="<?= !empty($item['avatar']) ? $item['avatar'] : "/img/avatars/50x50.png" ?>" alt="" />
				<p><img src="/img/comment.png" alt="" /><font class="content_JS"><?= $item['comment'] ?></font></p>
				<span class="created_JS"><?= Date::format($item['created'], "j M") ?></span>
				<? if($authUserID) :?>
				<div><? if($item['from_user'] == $authUserID || $user->admin ) :?><a class="remove_JS" rel="<?= $item['id']; ?>" href="#">Удалить</a><? endif; ?><a class="answer_JS" rel="<?= $item['id']; ?>" href="#">Ответить</a><a class="answerPrivate_JS" rel="<?= $item['id']; ?>" href="#">Написать личное сообщение</a></div>												
				<? endif; ?>
			</article>
			<? endif;?>
		<? endforeach;?><? endif;?>
		</div>
		<? if($authUserID) :?>
			<article class="comment comment_JS" id="user_<?= $authUserID ?>">
					<strong class="authUsername_JS"><?= $user->name ?></strong>
					<img class="authAvatar_JS" src="<?= ( !empty($user->avatar) ) ? $user->avatar : "/img/avatars/50x50.png" ?>" alt="" />
					<p><textarea class="input_JS" name=""></textarea></p>
					<a class="button button_JS" href="#">Комментировать</a>
			</article>
		<? endif; ?>	
	</aside>


	<script type="text/javascript">	

		new Comments({
			admin: <?= ($user) ? $user->admin : 0 ?>,
			pagination: true,
			pageID: <?= $articeleID ?>,
			limit: '<?= $limit ?>',
			count: <?= $articleCommentsCount ?>,
			addUrl: "/lovestory/comments/add",
			removeUrl: "/lovestory/comments/delete",
			getMoreUrl: "/lovestory/comments/list",
		
		});


	</script>


*/




var Comments = function(params){
	
	function Construct(params) {
	
		this.opt =  $.extend({	

			pageID: "", // Страница к которой прецеплены комменты, например ID статьи
		
			limit: 5,
			offset: 0,
			count: 0,
			
			put: 'up', // Куда класть новые комменты (up OR down)
		
			addUrl: "/", // URL контроллера добавления коммента
			removeUrl: "/", // URL контроллера удаления коммента
			answerUrl: "/", // URL контроллера ответного коммента
			answerPrivateUrl: "/", // URL контроллера приватного коммента			
			getMoreUrl: "/" // URL контроллера 'показать еще комменты'
			

		
		}, params);
		
		this.toUserID = 0;
		this.privateComment = 0;	
		
		this.countView = this.opt.limit; // счетчик видимых комментов на странице


		this.$el 	= $('#Comments_JS'); // Родительский (!) элемент контейнера с комментами

		this.$countBar = this.$el.find('.countBar_JS');// Count комментов
	
		this.$list = this.$el.find('.list_JS'); // Список комментов

		this.$items	= this.$el.find('.commentItem_JS');	// контейнер с комментом	
		this.$itemExample = this.$el.find('.commentExamle_JS'); // контейнер с экземляром коммента	
		
		this.$removeButton = this.$items.find('.remove_JS'); // Кнопка удадения коммента
		this.$answerButton = this.$items.find('.answer_JS'); // Кнопка ответного коммента
		this.$answerPrivateButton = this.$items.find('.answerPrivate_JS'); // Кнопка личного коммента
		
		//Блок отправки коммента
		
		var $authUser = this.$el.find('.comment_JS'); // контейнер блока отправки

		this.authUserID = ($authUser.length) ? $authUser.attr('id').substr(5) :false;
		this.authAvatar = trim(this.$el.find('.authAvatar_JS').attr('src')); 
		this.authUsername = trim(this.$el.find('.authUsername_JS').html());
		
		this.$input = this.$el.find('.input_JS');  // Поле отправки
		this.$button= this.$el.find('.button_JS');// Кнопка отправки	
		this.$buttonMore= this.$el.find('#getMore_JS');// Кнопка показать еще
		
		
		this.init();				
	}
	
	
	Construct.prototype = {
	
		init: function() { 
	
			var _self = this;

			if( this.opt.count == 0 || this.opt.count <= this.opt.limit) _self.$buttonMore.hide();
	
			this.$button.click(function(event){
				event.preventDefault();
				_self.submit();
				return false;
			});
					
			this.$removeButton.live('click',function(event){
				_self.remove( $(this).attr('rel') );
				return false;
			});
			
			this.$answerButton.live('click',function(event){
				_self.answer( $(this).attr('rel') );
				return false;
			});
			
			this.$answerPrivateButton.live('click',function(event){
				_self.answerPrivate( $(this).attr('rel') );
				return false;
			});
			
			this.$buttonMore.click(function(event){
				_self.get();
				return false;
			});
		
		},
		
		setClone: function($clone,data) {
			
			$clone.attr('class', 'commentItem_JS');				
			$clone.attr('id', 'comment_'+data.id);
			$clone.find('.created_JS').html( data.created );
			$clone.find('.avatar_JS').attr( 'src', data.avatar );
			$clone.find('.fromUserName_JS').html( data.fromUserName );
			$clone.find('.fromUserID_JS').html( data.fromUserID );
			$clone.find('.content_JS').html( data.content );
			$clone.find('.answer_JS').attr('rel',data.id );
			$clone.find('.answerPrivate_JS').attr('rel',data.id );
			
			if( this.authUserID != data.fromUserID ){

			
				$clone.find('.remove_JS').hide();
			}else{
				$clone.find('.remove_JS').attr('rel',data.id );
			}
			
			
		},
			
		submit: function() {
			
			var _self = this;
			
			var val = stripHTML(trim( this.$input.val() ));
			
			if( isEmpty(val) ) return;
			
			var params = {				
				 'content': val
				,'toUserID' : this.toUserID 
				,'privateComment' : this.privateComment 
				,'pageID' : this.opt.pageID
			}

				this.$button.attr('disabled', 'disabled');

			ajaxPost(this.opt.addUrl,params,function(data){
			
				if(data.error) return;
			
				var $clone = _self.$itemExample.clone();
				
				// докладываем в дату то что и так известно, чтобы вывести сразу
				data.content			= val;
				data.toUserID			= _self.toUserID;
				data.fromUserName 		= _self.authUsername;
				data.fromUserID 		= _self.authUserID;
				data.avatar 			= _self.authAvatar;
				data.created 			= getDate('j M') ;			
				data.privateComment 	= _self.privateComment;
				
				_self.setClone($clone,data);
				
	
				if(_self.opt.put ='down'){
					_self.$list.append( $clone );	
				}
				
				if(_self.opt.put ='up'){
					_self.$list.prepend( $clone );	
				}
				
				_self.$input.val('');	
				
				_self.$countBar.html( parseInt(_self.$countBar.html()) + 1 );				

				_self.$list.find('.commentItem_JS').show();
				
				_self.toUserID = 0;			
				_self.privateComment = 0;
				
				_self.$button.removeAttr('disabled')
				
				_self.opt.offset++;
	
			});
		},
		remove: function(id) { 
				
				var _self = this;
				
				ajaxPost(this.opt.removeUrl,{'id':id},function(data){
					
					 if(data.error) return;					 
					_self.$list.find('#comment_'+id).remove();
					_self.$countBar.html( parseInt(_self.$countBar.html()) - 1 );
				});	
		
		},
		answer: function(commentID) {
		
				var el = $('#comment_'+commentID);		
				this.toUserID  = parseInt(el.find('.fromUserID_JS').html());
	
				var answerToUserName = el.find('.fromUserName_JS').html();
				this.$input.val('@'+answerToUserName+' ');
		},
		answerPrivate: function(commentID) {

				var el = $('#comment_'+commentID);
				
				this.toUserID  = parseInt(el.find('.fromUserID_JS').html());	
				
				this.privateComment = true;	
				
				var answerPrivateToUserName = el.find('.fromUserName_JS').html();
				this.$input.val('@'+answerPrivateToUserName+' [личное] ');		
		},
		get: function() { 
	
				this.opt.offset += this.opt.limit;
				
				_self = this;
				
				var $clone;
				
				ajaxPost(this.opt.getMoreUrl,{'pageID':  this.opt.pageID, limit:this.opt.limit,offset:this.opt.offset},function(data){
					
					if( isEmpty(data) ) return;

					for( var i in data )
					{
						
						$clone = _self.$itemExample.clone(true);							
						_self.setClone($clone,data[i]);							
						_self.$list.append( $clone );						
						_self.countView++;	
						
					}
					
					_self.$list.find('.commentItem_JS').show();
						
					console.log(_self.countView );	
					console.log(_self.opt.count );	
						
						
					if( _self.countView == _self.opt.count ) { _self.$buttonMore.hide() };

				});
		}	
	};
	
	new Construct(params);	
	
};