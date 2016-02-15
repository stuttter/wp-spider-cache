/* global window, document, $, jQuery, ajaxurl, SpiderCache */

( function ( $ ) {
	"use strict";

	var $instanceStore,
		$refreshInstance,
		$instanceSelector,
		$noResults,
		$refreshResults,
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
			$instanceStore.html( SpiderCache.refreshing_results );
			$.ajax( {
				type : 'post',
				url  : ajaxurl,
				data : {
					action : 'jc-get-instance',
					nonce  : $el.data( 'nonce' ),
					name   : $val
				},
				cache   : false,
				success : function ( data ) {
					setTimeout( function() {
						if ( data ) {
							$instanceStore.html( data );
							$refreshInstance.show();
						} else {
							$instanceStore.html( SpiderCache.no_results );
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
		$instanceStore    = $( '.jc-contents' );
		$refreshInstance  = $( '.jc-refresh-instance' );
		$instanceSelector = $( '.jc-server-selector' );
		$noResults        = $( '.jc-no-results' );
		$refreshResults   = $( '.jc-refresh-results' );
		$showItem         = $( '#jc-show-item' );
		$searchResults    = $( '#jc-search-input' );

		$searchResults.keyup( function() {
			searchTable( this );
		} );

		$refreshInstance.click( function () {
			$instanceSelector.trigger( 'change' );
			return false;
		} );

		$instanceSelector.bind( 'change', handleChange );

		$( document.body )
			.on( 'click', '.jc-flush-group', function ( e ) {
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
			.on( 'click', '.jc-remove-item', function ( e ) {
				var elem = $( e.currentTarget );

				elem.parents( 'tr' ).addClass( 'row-updating' );

				$.ajax( {
					type    : 'post',
					url     : e.currentTarget.href,
					success : remove_item( elem[0] )
				} );
				return false;
			} )
			.on( 'click', '.jc-view-item', function ( e ) {
				$.ajax( {
					type    : 'post',
					url     : e.currentTarget.href,
					success : function ( data ) {
						$showItem.html( data );
						window.location.hash = 'jc-wrapper';
					}
				} );
				return false;
			} );
	} );
}( jQuery ) );