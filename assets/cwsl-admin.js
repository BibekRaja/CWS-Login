(function ($) {
	'use strict';

	$(function () {
		var frame;
		var $input = $('#cwsl_logo_attachment_id');
		var $preview = $('#cwsl-logo-preview');
		var $remove = $('#cwsl-remove-logo');

		$('#cwsl-select-logo').on('click', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: cwslAdmin.chooseLogo,
				button: { text: cwslAdmin.useLogo },
				multiple: false,
				library: { type: 'image' },
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var src =
					attachment.sizes && attachment.sizes.medium
						? attachment.sizes.medium.url
						: attachment.url;
				$input.val(attachment.id);
				$preview
					.empty()
					.append(
						$('<img />', {
							src: src,
							alt: '',
						})
					)
					.show();
				$remove.show();
			});
			frame.open();
		});

		$remove.on('click', function (e) {
			e.preventDefault();
			$input.val('0');
			$preview.empty().hide();
			$remove.hide();
		});
	});
}(jQuery));
