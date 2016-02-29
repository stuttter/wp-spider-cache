/* global window, document, $, jQuery, ajaxurl, WP_Spider_Cache */

( function ( $ ) {
	"use strict";

	var $instanceStore,
		$refreshInstance,
		$instanceSelector,
		$noResults,
		$refreshResults,
		$adminType,
		$showItem,
		$searchResults;

	function remove_group( el ) {
		return function () {
			var row = $( el ).closest( 'tr' );

			row.fadeOut( 500, function() {
				$instanceStore.children( 'tr' ).removeClass( 'row-updating' );
				row.remove();
			} );
		};
	}

	function remove_item( el ) {
		return function () {
			var key = $( el ).closest( 'div.item' );

			key.fadeOut( 500, function() {
				$instanceStore.children( 'tr' ).removeClass( 'row-updating' );
				key.remove();
			} );
		};
	}

	function handleChange( e ) {
		var $el  = $( e.currentTarget ),
			$val = $.trim( $el.val() );

		if ( $val ) {
			$refreshInstance.prop( 'disabled', true );
			$instanceStore.html( WP_Spider_Cache.refreshing_results );
			$.ajax( {
				type : 'post',
				url  : ajaxurl,
				data : {
					action : 'sc-get-instance',
					nonce  : $el.data( 'nonce' ),
					name   : $val,
					type   : $adminType.val()
				},
				cache   : false,
				success : function ( data ) {
					setTimeout( function() {
						if ( data ) {
							$instanceStore.html( data );
							$refreshInstance.show();
						} else {
							$instanceStore.html( WP_Spider_Cache.no_results );
						}
						$refreshInstance.prop( 'disabled', false );
					}, 500 );
				}
			} );
		}
	}

	function searchTable( element ) {
		var value = $( element ).val();

		$instanceStore.children( 'tr' ).each( function() {
			if ( $( this ).text().indexOf( value ) > -1 ) {
				$( this ).show();
			} else {
				$( this ).hide();
			}
		} );
	}

	$( document ).ready( function () {
		$instanceStore    = $( '.sc-contents' );
		$refreshInstance  = $( '.sc-refresh-instance' );
		$instanceSelector = $( '.sc-server-selector' );
		$noResults        = $( '.sc-no-results' );
		$refreshResults   = $( '.sc-refresh-results' );
		$adminType        = $( '#sc-admin-type' );
		$showItem         = $( '#sc-show-item' );
		$searchResults    = $( '#sc-search-input' );

		$searchResults.keyup( function() {
			searchTable( this );
		} );

		$refreshInstance.click( function () {
			$showItem.hide();
			$instanceSelector.trigger( 'change' );
			return false;
		} );

		$instanceSelector.bind( 'change', handleChange );

		$( document.body )
			.on( 'click', '.sc-flush-group', function ( e ) {
				var elem = $( e.currentTarget ),
					keys = [ ];

				elem.parents( 'td' ).next().find( 'div.item' ).each( function () {
					keys.push( $( this ).data( 'key' ) );
				} );

				elem.parents( 'tr' ).addClass( 'row-updating' );

				$.ajax( {
					type : 'post',
					url  : e.currentTarget.href,
					data : {
						keys : keys
					},
					success : remove_group( elem[0] )
				} );

				return false;
			} )
			.on( 'click', '.sc-remove-item', function ( e ) {
				var elem = $( e.currentTarget );

				elem.parents( 'tr' ).addClass( 'row-updating' );

				$.ajax( {
					type    : 'post',
					url     : e.currentTarget.href,
					success : remove_item( elem[0] )
				} );
				return false;
			} )
			.on( 'click', '.sc-view-item', function ( e ) {
				var elem = $( e.currentTarget );

				elem.parents( 'tr' ).addClass( 'row-updating' );

				$.ajax( {
					type    : 'post',
					url     : e.currentTarget.href,
					success : function ( data ) {
						$showItem.html( data );
						$showItem.show();
						elem.parents( 'tr' ).removeClass( 'row-updating' );
						window.location.hash = 'sc-wrapper';
					}
				} );
				return false;
			} );
	} );
}( jQuery ) );