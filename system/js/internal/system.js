$(function() {
	_FADE_SLOW = 4000;

	/**
	 * Seotoaster popup dialog
	 */
	$('a.tpopup').click(function(e) {
		e.preventDefault();
		var popup       = (top.$('#__tpopup').length) ? top.$('#__tpopup') : document.createElement('iframe');
		var popupWidth  = $(this).data('pwidth') || 960;
		var popupHeight = $(this).data('pheight') || 650;
		$(popup)
			.attr('src', $(this).data('url'))
			.attr('scrolling', 'no')
			.attr('frameborder', 'no')
			.attr('id', '__tpopup')
			.dialog({
				width     : popupWidth,   //960 480 / 320
				height    : popupHeight,  // 325
				resizable : false,
				modal     : true,
				open      : function() {
					$(popup).css({
						width    : popupWidth + 'px',
						height   : popupHeight + 'px',
						padding  : '0px',
						margin   : '0px',
						overflow : 'hidden'
					});
					$('.ui-dialog-titlebar').hide();
				},
				close     : function() {
					$(popup).remove();
				}
			});
	});

	$('.closebutton').click(function() {
		top.$('#__tpopup').dialog('close');
	})

	$('#ajax_msg').click(function(){
		$(this).fadeOut();
	})

	$('form._fajax').live('submit', function(e) {
		e.preventDefault();
		var ajaxMessage = $('#ajax_msg');
		var form        = $(this);
		$.ajax({
			url        : form.attr('action'),
			type       : 'post',
			dataType   : 'json',
			data       : form.serialize(),
			beforeSend : function() {
				ajaxMessage.fadeIn().removeClass('ui-state-error').addClass('success');
				$('#msg-text').text('Working...');
			},
			success : function(response) {
				if(!response.error) {
					if(form.hasClass('_reload')) {
						if(response.responseText.redirectTo != 'undefined') {
							top.location.href = $('#website_url').val() + response.responseText.redirectTo;
							return;
						}
						top.location.reload();
						return;
					}
					ajaxMessage.html(response.responseText).fadeOut(_FADE_SLOW);
				}
				else {
					ajaxMessage.removeClass('success').addClass('ui-state-error');
					$('#msg-text').replaceWith(response.responseText);
				}
			},
			error: function() {
				ajaxMessage.removeClass('success').addClass('ui-state-error');
				$('#msg-text').text('Error occured');
			}
		})
	})
});