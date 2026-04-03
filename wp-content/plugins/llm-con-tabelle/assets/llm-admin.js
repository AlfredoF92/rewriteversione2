( function ( $ ) {
	'use strict';

	function phraseCount() {
		return $( '#llm-phrases-list .llm-phrase-row' ).length;
	}

	function nextPhraseIndex() {
		var max = -1;
		$( '#llm-phrases-list .llm-phrase-row' ).each( function () {
			$( this )
				.find( 'textarea[name^="llm_phrases["]' )
				.first()
				.each( function () {
					var m = this.name.match( /llm_phrases\[(\d+)\]/ );
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
			return llmAdmin.emptyPhraseHint || '(vuoto)';
		}
		if ( t.length > 90 ) {
			return t.slice( 0, 87 ) + '…';
		}
		return t;
	}

	function updatePhrasePreview( $row ) {
		var v = $row.find( '.llm-phrase-interface' ).val();
		$row.find( '.llm-phrase-preview' ).text( truncatePreview( v ) );
	}

	function updatePhraseLabelsAndPreviews() {
		$( '#llm-phrases-list .llm-phrase-row' ).each( function ( i, row ) {
			var $row = $( row );
			$row.find( '.llm-phrase-num' ).text( String( i + 1 ) );
			updatePhrasePreview( $row );
		} );
	}

	function renumberPhraseNames() {
		$( '#llm-phrases-list .llm-phrase-row' ).each( function ( i, row ) {
			$( row )
				.find( 'textarea, input' )
				.each( function () {
					var n = this.name;
					if ( ! n || n.indexOf( 'llm_phrases[' ) !== 0 ) {
						return;
					}
					this.name = n.replace( /llm_phrases\[\d+]/, 'llm_phrases[' + i + ']' );
				} );
		} );
		updatePhraseLabelsAndPreviews();
		refreshMediaPositionSelects();
	}

	function refreshMediaPositionSelects() {
		var n = phraseCount();
		var $wrap = $( '#llm-media-list' );
		var labelTpl = $wrap.data( 'phrase-label-after' ) || 'After phrase %d';
		$wrap.find( 'select.llm-after-phrase' ).each( function () {
			var $sel = $( this );
			var v = $sel.val();
			$sel.empty();
			$sel.append(
				$( '<option></option>' )
					.attr( 'value', '-1' )
					.text( llmAdmin.beforeAllPhrases || 'Prima di tutte le frasi' )
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
		$row.find( '.llm-pick-image' ).on( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( {
				title: llmAdmin.selectImage,
				button: { text: llmAdmin.selectImage },
				multiple: false,
				library: { type: 'image' },
			} );
			var $r = $( this ).closest( '.llm-media-row' );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				if ( ! att || ! att.id ) {
					return;
				}
				$r.find( '.llm-attachment-id' ).val( att.id );
				var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
				var $img = $r.find( '.llm-media-thumb' );
				$img.empty();
				if ( url ) {
					$img.append( $( '<img />' ).attr( 'src', url ).attr( 'alt', '' ) );
				}
			} );
			frame.open();
		} );
		$row.find( '.llm-remove-media' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.llm-media-row' ).remove();
			renumberMediaNames();
		} );
	}

	function renumberMediaNames() {
		$( '#llm-media-list .llm-media-row' ).each( function ( i, row ) {
			$( row )
				.find( 'input, select' )
				.each( function () {
					var n = this.name;
					if ( ! n || n.indexOf( 'llm_media_blocks[' ) !== 0 ) {
						return;
					}
					this.name = n.replace( /llm_media_blocks\[[^\]]+]/, 'llm_media_blocks[' + i + ']' );
				} );
		} );
	}

	function addPhraseRow() {
		var tpl = $( '#llm-phrase-template' ).html();
		if ( ! tpl ) {
			return;
		}
		var idx = String( nextPhraseIndex() );
		var html = tpl.split( '{{IDX}}' ).join( idx ).split( '{{NUM}}' ).join( '1' );
		var $row = $( html );
		$( '#llm-phrases-list' ).append( $row );
		renumberPhraseNames();
	}

	function addMediaRow() {
		var tpl = $( '#llm-media-template' ).html();
		if ( ! tpl ) {
			return;
		}
		var idx = 'n' + Date.now();
		var html = tpl.split( '{{IDX}}' ).join( idx ).split( '{{NUM}}' ).join( '1' );
		var $row = $( html );
		$( '#llm-media-list' ).append( $row );
		bindMediaRow( $row );
		renumberMediaNames();
		refreshMediaPositionSelects();
		var $last = $( '#llm-media-list .llm-media-row' ).last();
		$last.find( 'select.llm-after-phrase' ).val( '-1' );
	}

	$( function () {
		if ( ! $( '#llm-phrases-list' ).length ) {
			return;
		}

		$( '#llm-phrases-list' ).sortable( {
			handle: '.llm-drag-handle',
			axis: 'y',
			update: function () {
				renumberPhraseNames();
			},
		} );

		$( '#llm-add-phrase' ).on( 'click', function ( e ) {
			e.preventDefault();
			addPhraseRow();
		} );

		$( '#llm-phrases-list' ).on( 'click', '.llm-remove-phrase', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.llm-phrase-row' ).remove();
			if ( ! $( '#llm-phrases-list .llm-phrase-row' ).length ) {
				addPhraseRow();
			}
			renumberPhraseNames();
		} );

		$( '#llm-phrases-list' ).on( 'input', '.llm-phrase-interface', function () {
			updatePhrasePreview( $( this ).closest( '.llm-phrase-row' ) );
		} );

		$( '#llm-media-list .llm-media-row' ).each( function () {
			bindMediaRow( $( this ) );
		} );

		$( '#llm-add-media' ).on( 'click', function ( e ) {
			e.preventDefault();
			addMediaRow();
		} );

		updatePhraseLabelsAndPreviews();
		refreshMediaPositionSelects();
	} );
}( jQuery ) );
