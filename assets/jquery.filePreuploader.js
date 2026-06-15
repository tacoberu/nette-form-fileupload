/**
 *	Dekorate file button for upload.
 *	Handler for sending ajax/iframe data.
 *
 *  Licensed under both the MIT license and the GNU GPLv2 (same as jQuery: http://jquery.org/license)
 *
 *	@author Martin Takáč <martin@takac.name>
 */
if (jQuery)(function($) {

	/**
	 * Plugin for ajax/iframe file upload.
	 */
	$.fn.filePreuploader = (function ()
	{

		/**
		 * From element WINDOW getting document
		 * @param WINDOW w
		 */
		function getDocumentFromWindow(w)
		{
			if (w.contentDocument) {
				return w.contentDocument;
			}
			else if (w.contentWindow) {
				return w.contentWindow.document;
			}
			else if (w.document) {
				return w.document;
			}
			else {
				throw ('Nothing!');
			}
		}



		/**
		 * Assert by empty jQuery selector.
		 */
		function assertEmpty(el, def)
		{
			if (el.length) {
				return el;
			}
			$.error("Empty selection for: " + def);
		}



		function deriveSelector(index, el)
		{
			return '__taco_uploader_' + index + '_' + Math.ceil(Math.random() * 9999999);
		}



		/**
		 * Inicializate function for instantion.
		 *
		 * @this Window
		 * @param self DOMElement
		 * @param context instance pluginu ?/ configuration this instance
		 */
		function init(self, context, index)
		{
			context.spinnerUrl || $.error("Unused require option 'spinnerUrl'.");

			// Doplnit id, pokud není.
			if ( ! self.id) {
				context.snippet = '#' + deriveSelector(index, self);
				self.setAttribute('id', context.snippet);
			}

			if ( ! context.snippet) {
				context.snippet = '#' + self.id;
			}

			var m = $(self);

			//  Click send to fileinput
			$(m).find(context.uploadWrapper)
				.click(function(env) {
				$(env.target).find(':file').click();
			});

			//  Send by ajax/iframe.
			$(m).find(context.uploadWrapper + ' :file')
				.on('change', function() {
				context.onChange(self);
			});

			//	Hide remove item
			$(m).find(':checkbox')
				.on('change', function() {
				context.onRemoveItem(self, $(this).parents('li.file'));
			});

			//	Hide has removed item
			$(m).find(':checkbox:checked').each(function(a,b) {
				context.onRemoveItem(self, $(this).parents('li.file'));
			});

			//  Auto send via special button.
			if (context.autoSubmitBy) {
				$(m).parents('form')
					.find(assertEmpty(context.autoSubmitBy, context.autoSubmitBy))
					.css({
						'visibility': 'hidden',
						'position' : 'absolute'
					});
			}

			return self;
		};



		/**
		 * Construct Function
		 *
		 * @this jQuerySelector
		 * @param string | object method
		 */
		function filePreuploader(method)
		{
			/**
			 *	Default configuration of plugin.
			 */
			this.defaults = {
				onChange: filePreuploader.prototype.onChange,
				onRemoveItem: filePreuploader.prototype.onRemoveItem,
				uploadWrapper: 'li.file.new-file',
				uploaderName: 'file-preuploader',
				autoSubmitBy: false,
				version: '0.4'
			};

			/**
			 *	Each all elements of selector.
			 */
			var _this = this;
			return this.each(function(index, el) {
				//	Instantion method
				if (typeof method === 'object' || !method) {
					return init(this, $.fn.extend(_this.defaults, method || {}), index);
				}
				//	Option method
				else if (filePreuploader.prototype[method]) {
					return filePreuploader.prototype[method].apply(this, Array.prototype.slice.call(arguments, 1));
				}
				else {
					$.error('Method ' + method + ' does not exist on jQuery.filePreuploader');
				}
			});
		}



		/**
		 * Handle event for change file input.
		 *
		 * @this instance pluginu.
		 * @param DOMElement original select.
		 */
		filePreuploader.prototype.onChange = function(component)
		{
			var form = $(component).parents('form'),
				context = this,
				iframe = $('<iframe/>', {
					'id': context.uploaderName,
					'name': context.uploaderName,
					'style': 'display: none',
					'width': 500,
					'height': 500
				});
			//  Data via iframe
			form.attr('target', this.uploaderName);
			form.append(iframe);

			//  Second getting iframe from DOM
			$('#' + this.uploaderName).on('load', function() {
				form.attr('target', null);

				//  Replace original content by from server.
				assertEmpty(form.find(context.snippet), context.snippet)
					.html($(context.snippet, getDocumentFromWindow(this)).html())
					.ready(function() {

						//  Click send to fileinput
						$(context.uploadWrapper, this).click(function(env) {
							$(env.target).find(':file').click();
						});

						//  Send by ajax/iframe.
						component = $(context.uploadWrapper, this);
						$(context.uploadWrapper + ' :file', this).on('change', function() {
							context.onChange(component);
						});

						//	Hide remove item
						$(':checkbox', this).on('change', function() {
							context.onRemoveItem(self, $(this).parents('li.file'));
						});

						//	Hide has removed item
						$(':checkbox:checked', this).each(function(a,b) {
							context.onRemoveItem(self, $(this).parents('li.file'));
						});
					});
			});

			//  Auto send via special button.
			if (this.autoSubmitBy) {
				form.find(context.uploadWrapper + ' :file')
					.parents('li.file')
					.css({
						'background-image': 'url("' + context.spinnerUrl + '")',
						'background-position': 'center',
						'background-repeat': 'no-repeat'
					});
				form.find(assertEmpty(this.autoSubmitBy, this.autoSubmitBy)).click();
			}
		}



		/**
		 * Handle event for change file input.
		 *
		 * @this instance pluginu.
		 * @param DOMElement original select.
		 */
		filePreuploader.prototype.onRemoveItem = function(component, item)
		{
			$(item).hide(500);
		}


		return filePreuploader;
	})();


})(jQuery);
