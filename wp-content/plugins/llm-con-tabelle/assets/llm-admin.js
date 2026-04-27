( function ( $ ) {
	'use strict';

	// ============================================================
	// Quick edit — immagine anteprima
	// ============================================================

	function llmQuickEditInit() {
		if ( typeof inlineEditPost === 'undefined' ) {
			return;
		}

		var origEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			origEdit.apply( this, arguments );

			var postId = typeof id === 'object'
				? parseInt( $( id ).closest( 'tr' ).attr( 'id' ).replace( 'post-', '' ), 10 )
				: parseInt( id, 10 );

			if ( isNaN( postId ) ) {
				return;
			}

			var $row       = $( '#post-' + postId );
			var $thumbCell = $row.find( '.llm-col-thumb' );
			if ( ! $thumbCell.length ) {
				return;
			}

			var thumbId  = parseInt( $thumbCell.data( 'thumbnail-id' ), 10 ) || 0;
			var thumbUrl = $thumbCell.data( 'thumbnail-url' ) || '';

			var $qeRow = $( '#edit-' + postId );
			$qeRow.find( '.llm-qe-thumbnail-id' ).val( thumbId > 0 ? thumbId : -1 );

			var $img = $qeRow.find( '.llm-qe-thumb-img' );
			$img.empty();
			if ( thumbUrl ) {
				$img.append( $( '<img />' ).attr( { src: thumbUrl, alt: '' } ) );
			}
		};
	}

	function llmPickImageForQE( $qeRow ) {
		var frame = wp.media( {
			title:    ( llmAdmin && llmAdmin.selectImage ) ? llmAdmin.selectImage : 'Scegli immagine',
			button:   { text: ( llmAdmin && llmAdmin.useImage ) ? llmAdmin.useImage : 'Usa questa immagine' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			if ( ! att || ! att.id ) {
				return;
			}
			$qeRow.find( '.llm-qe-thumbnail-id' ).val( att.id );
			var url = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
			var $img = $qeRow.find( '.llm-qe-thumb-img' );
			$img.empty();
			if ( url ) {
				$img.append( $( '<img />' ).attr( { src: url, alt: '' } ) );
			}
		} );

		frame.open();
	}

	// ============================================================
	// Editor completo — frasi e media
	// ============================================================

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
		var n        = phraseCount();
		var $wrap    = $( '#llm-media-list' );
		var labelTpl = $wrap.data( 'phrase-label-after' ) || 'After phrase %d';
		$wrap.find( 'select.llm-after-phrase' ).each( function () {
			var $sel = $( this );
			var v    = $sel.val();
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
				title:    llmAdmin.selectImage,
				button:   { text: llmAdmin.selectImage },
				multiple: false,
				library:  { type: 'image' },
			} );
			var $r = $( this ).closest( '.llm-media-row' );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				if ( ! att || ! att.id ) {
					return;
				}
				$r.find( '.llm-attachment-id' ).val( att.id );
				var url  = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
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
		var idx  = String( nextPhraseIndex() );
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
		var idx  = 'n' + Date.now();
		var html = tpl.split( '{{IDX}}' ).join( idx ).split( '{{NUM}}' ).join( '1' );
		var $row = $( html );
		$( '#llm-media-list' ).append( $row );
		bindMediaRow( $row );
		renumberMediaNames();
		refreshMediaPositionSelects();
		$( '#llm-media-list .llm-media-row' ).last().find( 'select.llm-after-phrase' ).val( '-1' );
	}

	// ============================================================
	// DOM ready
	// ============================================================

	$( function () {

		// ---- Quick edit ----
		$( document ).on( 'click', '.llm-qe-pick-image', function ( e ) {
			e.preventDefault();
			llmPickImageForQE( $( this ).closest( 'tr' ) );
		} );

		$( document ).on( 'click', '.llm-qe-remove-image', function ( e ) {
			e.preventDefault();
			var $qeRow = $( this ).closest( 'tr' );
			$qeRow.find( '.llm-qe-thumbnail-id' ).val( '0' );
			$qeRow.find( '.llm-qe-thumb-img' ).empty();
		} );

		llmQuickEditInit();

		// ---- Editor completo (solo se presente) ----
		if ( ! $( '#llm-phrases-list' ).length ) {
			return;
		}

		function replacePhrasesFromServer( phrases ) {
			var $list = $( '#llm-phrases-list' );
			var tpl   = $( '#llm-phrase-template' ).html();
			if ( ! tpl ) {
				return;
			}
			$list.empty();
			if ( ! phrases || ! phrases.length ) {
				addPhraseRow();
				return;
			}
			phrases.forEach( function ( row, i ) {
				var html = tpl.split( '{{IDX}}' ).join( String( i ) ).split( '{{NUM}}' ).join( String( i + 1 ) );
				var $row = $( html );
				var $ta  = $row.find( 'textarea' );
				if ( $ta.length >= 4 ) {
					$ta.eq( 0 ).val( row.interface || '' );
					$ta.eq( 1 ).val( row.target || '' );
					$ta.eq( 2 ).val( row.grammar || '' );
					$ta.eq( 3 ).val( row.alt || '' );
				}
				$list.append( $row );
			} );
			renumberPhraseNames();
			refreshMediaPositionSelects();
		}

		function llmCsvShowModal( show ) {
			var $m = $( '#llm-phrases-csv-modal' );
			if ( ! $m.length ) {
				return;
			}
			$m.prop( 'hidden', ! show );
			$m.attr( 'aria-hidden', show ? 'false' : 'true' );
			if ( show ) {
				$( document.body ).addClass( 'llm-csv-modal-open' );
			} else {
				$( document.body ).removeClass( 'llm-csv-modal-open' );
			}
		}

		function llmCsvResetModal() {
			$( '#llm-phrases-csv-preview-rows' ).empty();
			$( '#llm-phrases-csv-warnings' ).empty();
			$( '#llm-phrases-csv-summary' ).text( '' );
			$( '#llm-phrases-csv-log' ).text( '' );
			$( '#llm-phrases-csv-modal-step-preview' ).prop( 'hidden', false );
			$( '#llm-phrases-csv-modal-step-log' ).prop( 'hidden', true );
			$( '#llm-phrases-csv-modal-foot-preview' ).prop( 'hidden', false );
			$( '#llm-phrases-csv-modal-foot-done' ).prop( 'hidden', true );
			$( '#llm-phrases-csv-confirm' ).prop( 'disabled', false ).removeClass( 'disabled' );
			$( '#llm-phrases-csv-cancel' ).prop( 'disabled', false );
		}

		function llmCsvAppendLogLines( lines, done ) {
			var $log = $( '#llm-phrases-csv-log' );
			$log.text( '' );
			var i = 0;
			function step() {
				if ( i >= lines.length ) {
					if ( typeof done === 'function' ) {
						done();
					}
					return;
				}
				$log.append( ( i > 0 ? '\n' : '' ) + lines[ i ] );
				i += 1;
				window.setTimeout( step, 200 );
			}
			step();
		}

		var llmCsvPendingToken = '';

		function llmCsvCollapsePastePanel() {
			var $p = $( '#llm-phrases-csv-paste-panel' );
			var $t = $( '#llm-phrases-csv-paste-toggle' );
			if ( $p.length ) {
				$p.prop( 'hidden', true );
			}
			if ( $t.length ) {
				$t.attr( 'aria-expanded', 'false' );
			}
		}

		function llmCsvBuildPreviewFormData() {
			var fd = new FormData();
			fd.append( 'action', llmAdmin.csvPreviewAction || 'llm_story_phrases_preview_import' );
			fd.append( 'nonce', llmAdmin.csvNonce || '' );
			fd.append( 'nonce_post', llmAdmin.csvNoncePost || '' );
			fd.append( 'post_id', String( llmAdmin.postId || 0 ) );
			return fd;
		}

		function llmCsvApplyPreviewSuccess( res ) {
			llmCsvPendingToken = res.data.token || '';
			var prev   = res.data.preview || [];
			var labels = res.data.labels || {};
			var $tb    = $( '#llm-phrases-csv-preview-rows' );
			$tb.empty();
			prev.forEach( function ( row ) {
				var op = row.action === 'replace' ? ( labels.replace || 'Sostituzione' ) : ( labels.add || 'Aggiunta' );
				var $tr = $( '<tr/>' );
				$tr.append( $( '<td/>' ).text( String( row.position ) ) );
				$tr.append( $( '<td/>' ).append( $( '<span class="llm-csv-badge llm-csv-badge--' + row.action + '"/>' ).text( op ) ) );
				$tr.append( $( '<td class="llm-csv-cell-text"/>' ).text( row.interface || '' ) );
				$tr.append( $( '<td class="llm-csv-cell-text"/>' ).text( row.target || '' ) );
				$tr.append( $( '<td class="llm-csv-cell-text"/>' ).text( row.grammar || '' ) );
				$tr.append( $( '<td class="llm-csv-cell-text"/>' ).text( row.alt || '' ) );
				$tb.append( $tr );
			} );
			var sum = res.data.summary || { replace: 0, add: 0 };
			var sumTpl = llmAdmin.csvSummary || '';
			$( '#llm-phrases-csv-summary' ).text(
				sumTpl.replace( '%1$d', String( sum.replace || 0 ) ).replace( '%2$d', String( sum.add || 0 ) )
			);
			var $warn = $( '#llm-phrases-csv-warnings' );
			$warn.empty();
			( res.data.warnings || [] ).forEach( function ( w ) {
				$warn.append( $( '<li/>' ).text( w ) );
			} );
		}

		function llmCsvRunPreviewRequest( fd ) {
			llmCsvResetModal();
			$( '#llm-phrases-csv-summary' ).text( llmAdmin.csvLoading || '…' );
			llmCsvShowModal( true );

			$.ajax( {
				url:         llmAdmin.ajaxUrl,
				type:        'POST',
				data:        fd,
				dataType:    'json',
				processData: false,
				contentType: false,
			} )
				.done( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						var msg = ( res && res.data && res.data.message ) ? res.data.message : ( llmAdmin.csvErrGeneric || 'Errore' );
						alert( msg );
						llmCsvCollapsePastePanel();
						llmCsvShowModal( false );
						return;
					}
					llmCsvApplyPreviewSuccess( res );
				} )
				.fail( function ( xhr ) {
					var msg = llmAdmin.csvErrGeneric || 'Errore';
					if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						msg = xhr.responseJSON.data.message;
					}
					alert( msg );
					llmCsvCollapsePastePanel();
					llmCsvShowModal( false );
				} );
		}

		$( '#llm-phrases-list' ).sortable( {
			handle: '.llm-drag-handle',
			axis:   'y',
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

		if ( llmAdmin.postId && $( '#llm-phrases-csv-modal' ).length ) {
			$( '#llm-phrases-csv-paste-toggle' ).on( 'click', function ( e ) {
				e.preventDefault();
				var $panel = $( '#llm-phrases-csv-paste-panel' );
				var $btn   = $( this );
				if ( ! $panel.length ) {
					return;
				}
				var open = $panel.prop( 'hidden' );
				$panel.prop( 'hidden', ! open );
				$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
				if ( open ) {
					$( '#llm-phrases-csv-paste' ).trigger( 'focus' );
				}
			} );

			$( '#llm-phrases-csv-paste-preview' ).on( 'click', function ( e ) {
				e.preventDefault();
				var text = ( $( '#llm-phrases-csv-paste' ).val() || '' ).trim();
				if ( ! text ) {
					alert( llmAdmin.csvPasteEmpty || 'Incolla il CSV.' );
					return;
				}
				var fd = llmCsvBuildPreviewFormData();
				fd.append( 'csv_text', text );
				llmCsvRunPreviewRequest( fd );
			} );

			$( '#llm-phrases-csv-file' ).on( 'change', function () {
				var file = this.files && this.files[ 0 ];
				if ( ! file ) {
					return;
				}
				var fd = llmCsvBuildPreviewFormData();
				fd.append( 'file', file );
				llmCsvRunPreviewRequest( fd );
				$( '#llm-phrases-csv-file' ).val( '' );
			} );

			$( '#llm-phrases-csv-cancel, #llm-phrases-csv-modal-close, .llm-csv-modal__backdrop' ).on( 'click', function ( e ) {
				e.preventDefault();
				llmCsvPendingToken = '';
				llmCsvCollapsePastePanel();
				llmCsvShowModal( false );
			} );

			$( '#llm-phrases-csv-confirm' ).on( 'click', function ( e ) {
				e.preventDefault();
				if ( ! llmCsvPendingToken ) {
					return;
				}
				$( '#llm-phrases-csv-confirm' ).prop( 'disabled', true ).addClass( 'disabled' );
				$( '#llm-phrases-csv-cancel' ).prop( 'disabled', true );

				$( '#llm-phrases-csv-modal-step-preview' ).prop( 'hidden', true );
				$( '#llm-phrases-csv-modal-step-log' ).prop( 'hidden', false );
				$( '#llm-phrases-csv-modal-foot-preview' ).prop( 'hidden', true );
				$( '#llm-phrases-csv-log' ).text( '' );

				$.ajax( {
					url:      llmAdmin.ajaxUrl,
					type:     'POST',
					dataType: 'json',
					data:     {
						action:     llmAdmin.csvCommitAction || 'llm_story_phrases_commit_import',
						nonce:      llmAdmin.csvNonce || '',
						nonce_post: llmAdmin.csvNoncePost || '',
						post_id:    llmAdmin.postId,
						token:      llmCsvPendingToken,
					},
				} )
					.done( function ( res ) {
						if ( ! res || ! res.success || ! res.data ) {
							var msg = ( res && res.data && res.data.message ) ? res.data.message : ( llmAdmin.csvErrGeneric || 'Errore' );
							alert( msg );
							llmCsvResetModal();
							llmCsvCollapsePastePanel();
							llmCsvShowModal( false );
							return;
						}
						var lines = res.data.log || [];
						llmCsvAppendLogLines( lines, function () {
							if ( res.data.phrases ) {
								replacePhrasesFromServer( res.data.phrases );
							}
							llmCsvPendingToken = '';
							$( '#llm-phrases-csv-paste' ).val( '' );
							llmCsvCollapsePastePanel();
							$( '#llm-phrases-csv-modal-foot-done' ).prop( 'hidden', false );
						} );
					} )
					.fail( function ( xhr ) {
						var msg = llmAdmin.csvErrGeneric || 'Errore';
						if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
							msg = xhr.responseJSON.data.message;
						}
						alert( msg );
						llmCsvResetModal();
						llmCsvCollapsePastePanel();
						llmCsvShowModal( false );
					} );
			} );

			$( '#llm-phrases-csv-done' ).on( 'click', function ( e ) {
				e.preventDefault();
				llmCsvResetModal();
				llmCsvCollapsePastePanel();
				llmCsvShowModal( false );
			} );
		}
	} );

}( jQuery ) );
