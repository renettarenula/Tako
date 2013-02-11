/*
Copyright (C) <2013> <Ren Aysha>

This file is part of Tako.

Tako is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Tako is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Tako.  If not, see <http://www.gnu.org/licenses/>.
*/
jQuery(document).ready(function($) {
	var tako_dropdown = $('#tako_post_type'),
		tako_current_comment = $('#tako_current_comment').text(),
		tako_dropdown_list = $('#tako_dropdown_list'),
		spinner = $('#tako_spinner');
	tako_dropdown.change(function () {
		spinner.show(); // show spinner
 		current = $(this).find('option:selected').text();
 		// ajax starts here!
  		var data = {
			action: 'tako_chosen_post_type',
			postype: current, // We pass php values differently!
			post_id: tako_current_comment,
			tako_ajax_nonce: tako_object.tako_ajax_nonce
		};
		// We can also pass the url value separately from ajaxurl for front end AJAX implementations
		jQuery.post(ajaxurl, data, function(response) {
			tako_dropdown_list.html(response);
			spinner.hide(); // hide spinner
		}); 
	}).change();
});


