(function($) {
	$('document').ready(function() {
		var yetowohai = $("#yetowohai",parent.document);
		var statusPanel = yetowohai.children('.yetowohai_status');
		var progress = statusPanel.children('.progress');
		var complete = statusPanel.children('.complete');
		var fail = statusPanel.children('.fail');
		var button = yetowohai.find('button');
		var description = statusPanel.children('.description');
		var status = 0;
		button.on('click', function() {
			status = 1;
			button.hide();
			
			statusPanel.show();
			progress.show();
			var data = {};
			yetowohai.find('form').serializeArray().forEach(function(item) { data[item.name] = item.value});
			
			//setTimeout(function() {
				
				$.post(CtBuilderAjax.ajaxUrl, data, function(response) {
					
					if(response['errorMessage']) {
						fail.show();
						description.append('<p class="error">'+response.errorMessage+'</p>');
					}
 					else {
						iframeScope.addComponentFromSource(JSON.stringify(response.oxygen), false, '', '');
						complete.show();
						// update success message
						if(response.successMessage) {
							description.append('<p class="error">'+response.successMessage+'</p>');
						}
					}
					

				}).fail(function(response) {
					fail.show();
					description.append('<p class="error">'+response.responseText+'</p>');
					

				}).always(function(response) {
					progress.hide();
					if(response['logId'] && response['requestId']) {
						description.append('<hr />');
						description.append('<p>If you wish to report this instance. Provide the following info</p>');
						description.append('<p class="small">logId: '+response['logId']+'</p>');
						description.append('<p class="small">requestId: '+response['requestId']+'</p>');
						description.append('<hr />');
					}
					description.append('<p>Re-open this modal before issuing a new request.</p>');
					status = 0;
				});

			//}, 1000);

			//yetowohai.hide();
		})
		
		iframeScope.yetowohaiclose = function() {
			if(status === 1) {
				if(!confirm('The request is in progress. If you close this panel, the request will continue running in the background')) {
					return;
				}
			}
			yetowohai.hide();
		}

		iframeScope.yetowohai = function() {
			description.html('');
			progress.hide();
			complete.hide();
			fail.hide();
			statusPanel.hide();
			button.show();
			yetowohai.show();
		}
	})
})(jQuery);