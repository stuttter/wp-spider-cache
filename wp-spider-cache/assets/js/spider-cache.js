/* global window, document, $, jQuery, ajaxurl, WP_Spider_Cache */

( function ( $ ) {
	"use strict";

	var $instanceStore,
		$refreshInstance,
		$instanceSelector,
		$adminType,
		$showItem,
		$searchResults,
		$modalWindow;

	/**
	 *
	 * @param {data} e
	 * @param {element} elem
	 * @returns {void}
	 */
	function maybe_remove_group( e, elem ) {
		var result = $.parseJSON( e ),
			row    = elem.parents( 'tr' ),
			key    = row;

		setTimeout( function() {
			if ( result.success ) {
				key.fadeOut( 500, function() {
					key.remove();
				} );
			}

			row.removeClass( 'row-updating' );
		}, 500 );
	}

	/**
	 * Maybe remove a cache key item from the list-table
	 *
	 * @param {data} e
	 * @param {element} elem
	 * @returns {void}
	 */
	function maybe_remove_item( e, elem ) {
		var result = $.parseJSON( e ),
			row    = elem.parents( 'tr' ),
			col    = elem.parents( 'td' );

		if ( 1 === col.children( 'div.item' ).length ) {
			var key = row;
		} else {
			var key = elem.parents( 'div.item' );
		}

		setTimeout( function() {
			if ( result.success ) {
				key.fadeOut( 500, function() {
					key.remove();
				} );
			}

			row.removeClass( 'row-updating' );
		}, 500 );
	}

	function handleChange( e ) {
		var el  = $( e.currentTarget ),
			val = $.trim( el.val() );

		if ( val ) {
			$refreshInstance.prop( 'disabled', true );
			$instanceStore.html( WP_Spider_Cache.refreshing_results );
			$.ajax( {
				type : 'post',
				url  : ajaxurl,
				data : {
					action : 'sc-get-instance',
					nonce  : el.data( 'nonce' ),
					name   : val,
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
				},
				error : function () {
					$refreshInstance.prop( 'disabled', false );
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
		$adminType        = $( '#sc-admin-type' );
		$showItem         = $( '#sc-show-item' );
		$searchResults    = $( '#sc-search-input' );

		$searchResults.keyup( function() {
			searchTable( this );
		} );

		$refreshInstance.click( function () {
			$instanceSelector.trigger( 'change' );
			return false;
		} );

		$instanceSelector.bind( 'change', handleChange );

		$( document.body )
			.on( 'click', '.sc-flush-group', function ( e ) {
				var elem = $( e.currentTarget ),
					keys = [];

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
					success : function ( e ) {
						maybe_remove_group( e, elem );
					}
				} );

				return false;
			} )
			.on( 'click', '.sc-remove-item', function ( e ) {
				var elem = $( e.currentTarget );

				elem.parents( 'tr' ).addClass( 'row-updating' );

				$.ajax( {
					type    : 'post',
					url     : e.currentTarget.href,
					success : function ( e ) {
						maybe_remove_item( e, elem );
					}
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
						$modalWindow = $( '#TB_ajaxContent' );
						$showItem.html( data ).detach().appendTo( $modalWindow );
						elem.parents( 'tr' ).removeClass( 'row-updating' );
					}
				} );
				return false;
			} );
	} );
}( jQuery ) );
