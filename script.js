function dw2pdf_export(allConfig,overriddenConfig){
	var $ = jQuery, $grayout, $container, $dialog, $content, $error_area, $close,
		// these are private variables, to make it easier to alter add additional features.
		export_type = 'export_pdf', template_config = {}, location =JSINFO.id, rev = (window.location.search.match(/\brev=(\d+)\b/)||[null,null])[1],
		display_popup = function(){	// this function is called only if settingstree plugin is installed, hence we can borrow the popup layers to display progress.
			if (($content||[]).length === 0){	// display the dialog if not shown already.
				$grayout = $('<div id="settingstree_grayout"></div>').appendTo('body');
				$container = $('<div id="settingstree_export_popup_container"></div>').appendTo($grayout);
				$dialog = $('<div class="settingstree_export_popup_layer"></div>').appendTo($container);
				$close = $('<div class="settingstree_export_popup_close_button"> x </div>').appendTo($container).on('click',function(e){close_popup()}),
				$error_area = $('<div class="settingstree_error_area"></div>').appendTo($dialog);
				$content = $('<div class="dialog_content"></div>').appendTo($dialog);
			}
		},
		close_popup = function(){
			if (($grayout||[]).length){
				$grayout.remove();
			}
		},
		export_call = function(){
			/* Tell the server we want to export. 
			 * It will allways give us back html to show: 
			 *   - display errors, 
			 *   - display template-config for extended templates (soon...)
			 *   - display progress. (actually just 'preparing' -> 'gathering content' -> 'converting' -> 'done')
			 */
			$.post(DOKU_BASE + 'lib/exe/ajax.php',
				{ 	call:'plugin_dw2pdf', 
					operation: 'export', 	// we want to export (or at least try to, it may result in a config-popup for extended template settings)
					type: export_type ,		// we want the action 'export_pdf'. later may other options be added.
					location: location,		// tell the server the current location.
					rev: rev,				// tell the server the current revision (or null if default)
					config:overriddenConfig, // we only need to submit the overridden settings, as the rest if the same as the getExportConfig will get.
					template: template_config,
				},
				function(r){
//					if (r.token) token = r.token;	// Do we need sectok for this as well?
					display_popup();
					if (r.html){ $content.html(r.html);	}
					if (r.error){	$error_area.html(("<div class='error'>"+(r.msg||"fail")+"</div>")); }
					else {$dialog.css({height: '100%'}); $content.css({height: $dialog.height()-4, width: $dialog.width()-4})}	// set to full size.
				}
			);	
		};
		
	//NOTE: if additional options/features needs to be added (e.g. dialog to choose export type, set book_title etc) then here is the place for that, before calling the export ajax
	export_call();
			
};
