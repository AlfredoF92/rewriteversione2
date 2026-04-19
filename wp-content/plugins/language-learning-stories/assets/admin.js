( function ( $ ) {
	'use strict';

	function phraseCount() {
		return $( '#lls-phrases-list .lls-phrase-row' ).length;
	}

	function nextPhraseIndex() {
		var max = -1;
		$( '#lls-phrases-list .lls-phrase-row' ).each( function () {
			$( this )
				.find( 'textarea[name^="lls_phrases["]' )
				.first()
				.each( function () {
					var m = this.name.match( /lls_phrases\[(\d+)\]/ );
					if ( m && parseInt( m[ 1 ], 10 ) > max ) {
						max = parseInt( m[ 1 ], 10 );
					}
				} );
		} );
		return max + 1;
	}

	function truncatePreview( text ) {
		var t = ( text || '' ).replace( /\s+/g, ' ' ).trim();
		if ( ! t ) {
			return llsAdmin.emptyPhraseHint || '(vuoto)';
		}
		if ( t.length > 90 ) {
			return t.slice( 0, 87 ) + '…';
		}
		return t;
	}

	function updatePhrasePreview( $row ) {
		var v = $row.find( '.lls-phrase-interface' ).val();
		$row.find( '.lls-phrase-preview' ).text( truncatePreview( v ) );
	}

	function updatePhraseLabelsAndPreviews() {
		$( '#lls-phrases-list .lls-phrase-row' ).each( function ( i, row ) {
			var $row = $( row );
			$row.find( '.lls-phrase-num' ).text( String( i + 1 ) );
			updatePhrasePreview( $row );
		} );
	}

	function renumberPhraseNames() {
		$( '#lls-phrases-list .lls-phrase-row' ).each( function ( i, row ) {
			$( row )
				.find( 'textarea, input' )
				.each( function () {
					var n = this.name;
					if ( ! n || n.indexOf( 'lls_phrases[' ) !== 0 ) {
						return;
					}
					this.name = n.replace( /lls_phrases\[\d+]/, 'lls_phrases[' + i + ']' );
				} );
		} );
		updatePhraseLabelsAndPreviews();
		refreshMediaPositionSelects();
	}

	function refreshMediaPositionSelects() {
		var n = phraseCount();
		var $wrap = $( '#lls-media-list' );
		var labelTpl = $wrap.data( 'phrase-label-after' ) || 'After phrase %d';
		$wrap.find( 'select.lls-after-phrase' ).each( function () {
			var $sel = $( this );
			var v = $sel.val();
			$sel.empty();
			$sel.append(
				$( '<option></option>' )
					.attr( 'value', '-1' )
					.text( llsAdmin.beforeAllPhrases || 'Prima di tutte le frasi' )
			);
			var i;
			for ( i = 0; i < n; i++ ) {
				$sel.append(
					$( '<option></option>' )
						.attr( 'value', String( i ) )
						.text( labelTpl.replace( '%d', String( i + 1 ) ) )
				);
			}
			if ( $sel.find( 'option[value="' + v + '"]' ).length ) {
				$sel.val( v );
			} else {
				$sel.val( '-1' );
			}
		} );
	}

	function bindMediaRow( $row ) {
		$row.find( '.lls-pick-image' ).on( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title: llsAdmin.selectImage,
				button: { text: llsAdmin.selectImage },
				multiple: false,
				library: { type: 'image' },
			} );
			var $r = $( this ).closest( '.lls-media-row' );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				if ( ! att || ! att.id ) {
					return;
				}
				$r.find( '.lls-attachment-id' ).val( att.id );
				var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
				var $img = $r.find( '.lls-media-thumb' );
				$img.empty();
				if ( url ) {
					$img.append( $( '<img />' ).attr( 'src', url ).attr( 'alt', '' ) );
				}
			} );
			frame.open();
		} );
		$row.find( '.lls-remove-media' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.lls-media-row' ).remove();
			renumberMediaNames();
		} );
	}

	function renumberMediaNames() {
		$( '#lls-media-list .lls-media-row' ).each( function ( i, row ) {
			$( row )
				.find( 'input, select' )
				.each( function () {
					var n = this.name;
					if ( ! n || n.indexOf( 'lls_media_blocks[' ) !== 0 ) {
						return;
					}
					this.name = n.replace( /lls_media_blocks\[[^\]]+]/, 'lls_media_blocks[' + i + ']' );
				} );
		} );
	}

	function addPhraseRow() {
		var tpl = $( '#lls-phrase-template' ).html();
		if ( ! tpl ) {
			return;
		}
		var idx = String( nextPhraseIndex() );
		var html = tpl.split( '{{IDX}}' ).join( idx ).split( '{{NUM}}' ).join( '1' );
		var $row = $( html );
		$( '#lls-phrases-list' ).append( $row );
		renumberPhraseNames();
	}

	function addMediaRow() {
		var tpl = $( '#lls-media-template' ).html();
		if ( ! tpl ) {
			return;
		}
		var idx = 'n' + Date.now();
		var html = tpl.split( '{{IDX}}' ).join( idx ).split( '{{NUM}}' ).join( '1' );
		var $row = $( html );
		$( '#lls-media-list' ).append( $row );
		bindMediaRow( $row );
		renumberMediaNames();
		refreshMediaPositionSelects();
		var $last = $( '#lls-media-list .lls-media-row' ).last();
		$last.find( 'select.lls-after-phrase' ).val( '-1' );
	}

	$( function () {
		if ( ! $( '#lls-phrases-list' ).length ) {
			return;
		}

		$( '#lls-phrases-list' ).sortable( {
			handle: '.lls-drag-handle',
			axis: 'y',
			update: function () {
				renumberPhraseNames();
			},
		} );

		$( '#lls-add-phrase' ).on( 'click', function ( e ) {
			e.preventDefault();
			addPhraseRow();
		} );

		$( '#lls-phrases-list' ).on( 'click', '.lls-remove-phrase', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.lls-phrase-row' ).remove();
			if ( ! $( '#lls-phrases-list .lls-phrase-row' ).length ) {
				addPhraseRow();
			}
			renumberPhraseNames();
		} );

		$( '#lls-phrases-list' ).on( 'input', '.lls-phrase-interface', function () {
			updatePhrasePreview( $( this ).closest( '.lls-phrase-row' ) );
		} );

		$( '#lls-media-list .lls-media-row' ).each( function () {
			bindMediaRow( $( this ) );
		} );

		$( '#lls-add-media' ).on( 'click', function ( e ) {
			e.preventDefault();
			addMediaRow();
		} );

		updatePhraseLabelsAndPreviews();
		refreshMediaPositionSelects();
	} );
}( jQuery ) );
