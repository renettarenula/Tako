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
	
	var takoDropdown = {
		
		takoPostType: $('#tako_post_type'),
		takoCurrentPost: $('#tako_current_comment').text(),
		takoSelectBox: $('#tako_post'),
		spinner: $('#tako_spinner'),

		init: function() {
			this.takoGetPostType();
		},

		// Calls jQuery.post when a specific post type is chosen
		takoGetPostType: function() {
			var self = this;

			this.takoPostType.change(function () {
				self.spinner.show();
				var current = $(this).find( 'option:selected' ).text();
				self.takoProcessData( self.takoData( current ) );

			}).change();
		},

		// The data that will be passed to jQuery.post.
		// Parameter is the current post type chosen.
		// Data are WP callback, current post type, and nonce.
		// Callback will query post titles based on current post type.
		takoData: function( current ) {
			var data = {
				action: 'tako_chosen_post_type',
				postype: current,
				tako_ajax_nonce: tako_object.tako_ajax_nonce 
			};
			
			return data;
		},

		// jQuery.post that dynamically returns data to the dropdown box.
		// Harvesthq's chosen.js is used here in order to make dropdown box prettier!
		takoProcessData: function( data ) {
			var self = this;
			
			jQuery.post( ajaxurl, data, function( response ) {
				var responses = JSON.parse( response );
				
				// For updating options in the dropdown.
				// Empty the select box so that previous results (posts of other post types)
				// won't show. 
				self.takoSelectBox.empty();
				
				$.map( responses, function( item ) {
					self.takoSelectBox.append( '<option value="' + item.ID + '">' + item.title + '</option>' );
				});
				
				self.takoSelectBox.val( self.takoCurrentPost );
				self.takoSelectBox.chosen( { width: '30%' } );
				
				// Chosen needs to pick up changes when dropdown is emptied.
				// Rebuild Chosen and update the contents of dropdown.
				self.takoSelectBox.trigger( "liszt:updated" );

				self.spinner.hide();

			});
		}
	};

	takoDropdown.init();
});