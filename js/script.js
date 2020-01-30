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
		

		function removeCustomCSSParams(selector, customcss) {
												    
	    	if(customcss !== '') {
	    		let exploded = customcss.split(';').map(function(p) {return p.trim()});
	    		exploded.forEach(function(pline) {
	    			let splitted = pline.split(':');
	    			let key = splitted[0].trim();
	    			if(key !== '' && selector[key]) {
	    				delete(selector[key]);
	    			}
	    		})
	    	}
	    
	    	return selector;
	    }

		function processParameters(parameters) {
			for(let k in parameters) {
				if(k.substr(0, 7) === 'nbf_cs_') {
					let customcss = parameters['custom-css'] || '';
					customcss = customcss+k.substr(7)+':'+parameters[k]+";\n";
					parameters['custom-css'] = customcss;
					
					delete(parameters[k]);
				}
			}

			return parameters;
		}

		function goAllParams(options) {
			for(let optionKey in options) {
				if(['ct_id', 'ct_parent', 'ct_selector'].indexOf(optionKey) !== -1) {
					continue;
				}
				if(optionKey === 'media') {
					for(let mediaKey in options[optionKey]) {
						for(let mediaOptionkey in options[optionKey][mediaKey]) {
							if(typeof(options[optionKey][mediaKey][mediaOptionkey]) === 'object') {
								options[optionKey][mediaKey][mediaOptionkey] = processParameters(options[optionKey][mediaKey][mediaOptionkey]);
							}
						}
					}
					continue;
				}
				if(typeof(options[optionKey]) === 'object') {
					options[optionKey] = processParameters(options[optionKey]);
				}
			}

			return options;
		}

		function processTree(items) {
			
			items.forEach(function(item) {
				
				// generate custom-css
				
				item.options = goAllParams(item.options);

				if(item.children) {
					item.children = processTree(item.children);
				}
			})

			return items;
		}

		button.on('click', function() {
			status = 1;
			button.hide();

			fail.hide();
			complete.hide();
			description.html('');
			
			statusPanel.show();
			progress.show();
			var data = {};
			yetowohai.find('form').serializeArray().forEach(function(item) { data[item.name] = item.value});
			
			//setTimeout(function() {
				
				$.post(CtBuilderAjax.ajaxUrl, data, function(response) {
					
					if(!response || response['errorMessage']) {
						fail.show();
						description.append('<p class="error">'+((response && response.errorMessage)?response.errorMessage:'Unknown error occured')+'</p>');
					}
 					else {

 						// process the incoming tree
 						let processedTree = processTree([response.oxygen.component]);
 						response.oxygen.component = processedTree[0];
 						// merge classes
 						// separate classes and selectors
 						let classes = {};
 						let selectors = {};
 						for(let i in response.oxygen.classes) {
 							if(i.substr(0, 5) === 'nbfs_' && response.oxygen.classes[i]['comesbefore']) {
 								selectors[i] = response.oxygen.classes[i];
 							} else {
 								delete(response.oxygen.classes[i]['key']);
 								classes[i] = goAllParams(response.oxygen.classes[i]);
 							}
 						}

 						// merge classes
 						iframeScope.classes = Object.assign(iframeScope.classes, classes);

 						// now merge selectors in order
 						
 						classes = {};
 						let order = {};
 						
 						for( let i in selectors ) {
 							let comebefore = selectors[i]['comesbefore'];
							order[comebefore] = order[comebefore] || [];
							delete(selectors[i]['comesbefore']);
							order[comebefore].push(selectors[i]);
 						}

 						for(let i in iframeScope.classes) {

 							if(order[i]) {
 								order[i].forEach(function(selector) {
 									if(!iframeScope.classes[selector['key']]) {
 										// remove css params from selector that are defined in the class
 										
 										// lets try for original first
 										// TODO: for all states, media
 										for(let optionKey in selector) {
 											if(optionKey === 'key') {
 												continue;
 											}
 											if(optionKey === 'media') {
 												for(let mediaKey in selector[optionKey]) {
 													for(let mediaOptionkey in selector[optionKey][mediaKey]) {
 														if( typeof(selector[optionKey][mediaKey][mediaOptionkey]) === 'object'
 															&& iframeScope.classes[i][optionKey]
															&& iframeScope.classes[i][optionKey][mediaKey]
															&& iframeScope.classes[i][optionKey][mediaKey][mediaOptionkey]
 															) {

			 												let commonkeys = Object.keys(selector[optionKey][mediaKey][mediaOptionkey]).concat(Object.keys(iframeScope.classes[i][optionKey][mediaKey][mediaOptionkey])).sort().reduce(function (r, a, i, aa) {
														        if (i && aa[i - 1] === a) {
														            r.push(a);
														        }
														        return r;
														    }, []);

														    commonkeys.forEach(function(commonkey) {
														    	delete(selector[optionKey][mediaKey][mediaOptionkey][commonkey]);
														    })

														    // for a given class, get its param keys, and delete those keys from the selector
														    if(iframeScope.classes[i][optionKey][mediaKey][mediaOptionkey]['custom-css']) {
														    	selector[optionKey][mediaKey][mediaOptionkey] = removeCustomCSSParams(selector[optionKey][mediaKey][mediaOptionkey], iframeScope.classes[i][optionKey][mediaKey][mediaOptionkey]['custom-css'].trim())
														    }
			 											}
 													}
 												}
 												continue;
 											}

 											if(typeof(selector[optionKey]) === 'object'
 												&& iframeScope.classes[i][optionKey]) {
 												
 												let commonkeys = Object.keys(selector[optionKey]).concat(Object.keys(iframeScope.classes[i][optionKey])).sort().reduce(function (r, a, i, aa) {
											        if (i && aa[i - 1] === a) {
											            r.push(a);
											        }
											        return r;
											    }, []);

											    commonkeys.forEach(function(commonkey) {
											    	delete(selector[optionKey][commonkey]);
											    })

											    // for a given class, get its param keys, and delete those keys from the selector
											    if(iframeScope.classes[i][optionKey]['custom-css']) {
											    	selector[optionKey] = removeCustomCSSParams(selector[optionKey], iframeScope.classes[i][optionKey]['custom-css'].trim())
											    }
												
 											}
 										}

 										
									    let selectorKey = selector['key'];
									    delete(selector['key']);
 										classes[selectorKey] = goAllParams(selector);
 									}
 								})
 							}

 							classes[i] = iframeScope.classes[i];
 						}

 						iframeScope.classes = classes;


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
					//description.append('<p>Re-open this modal before issuing a new request.</p>');
					status = 0;
					button.show();
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
			progress.hide();
			complete.hide();
			fail.hide();
			button.show();
			yetowohai.show();
		}
	})
})(jQuery);