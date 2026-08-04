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
	// Aggiorna form admin dopo import storia completa
	// ============================================================

	function llmReplacePhrasesInEditor( phrases ) {
		var $list = $( '#llm-phrases-list' );
		var tpl   = $( '#llm-phrase-template' ).html();
		if ( ! $list.length || ! tpl ) {
			return;
		}

		$list.empty();
		if ( ! phrases || ! phrases.length ) {
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

		$list.find( '.llm-phrase-row' ).each( function ( i, row ) {
			var $row = $( row );
			$row.find( '.llm-phrase-num' ).text( String( i + 1 ) );
			$row.find( 'textarea, input' ).each( function () {
				var n = this.name;
				if ( n && n.indexOf( 'llm_phrases[' ) === 0 ) {
					this.name = n.replace( /llm_phrases\[\d+]/, 'llm_phrases[' + i + ']' );
				}
			} );
			var iface = $row.find( '.llm-phrase-interface' ).val();
			var preview = ( iface || '' ).replace( /\s+/g, ' ' ).trim();
			if ( preview.length > 90 ) {
				preview = preview.slice( 0, 87 ) + '…';
			}
			$row.find( '.llm-phrase-preview' ).text( preview || '(vuoto)' );
		} );
	}

	function llmApplyImportedStoryToForm( form, phrases ) {
		if ( ! form ) {
			return;
		}

		if ( form.title && $( '#title' ).length ) {
			$( '#title' ).val( form.title );
		}
		if ( form.known_lang ) {
			$( '#llm_known_lang' ).val( form.known_lang );
		}
		if ( form.target_lang ) {
			$( '#llm_target_lang' ).val( form.target_lang );
		}
		if ( typeof form.title_target === 'string' ) {
			$( '#llm_title_target_lang' ).val( form.title_target );
		}
		if ( typeof form.story_plot === 'string' ) {
			$( '#llm_story_plot' ).val( form.story_plot );
		}
		if ( typeof form.story_intro === 'string' ) {
			$( '#llm_story_intro' ).val( form.story_intro );
		}
		if ( typeof form.story_finale === 'string' ) {
			$( '#llm_story_finale' ).val( form.story_finale );
		}
		if ( typeof form.story_card_text === 'string' ) {
			$( '#llm_story_card_text' ).val( form.story_card_text );
		}
		if ( typeof form.story_cefr_level === 'string' ) {
			$( '#llm_story_cefr_level' ).val( form.story_cefr_level );
		}
		if ( typeof form.story_grammar_topics === 'string' ) {
			$( '#llm_story_grammar_topics' ).val( form.story_grammar_topics );
		}

		if ( form.category_id ) {
			$( '#categorychecklist input[type="checkbox"]' ).prop( 'checked', false );
			$( '#in-category-' + form.category_id ).prop( 'checked', true );
			$( '#categorychecklist input[value="' + form.category_id + '"]' ).prop( 'checked', true );
		}

		if ( phrases && phrases.length ) {
			llmReplacePhrasesInEditor( phrases );
		}

		$( '#llm_known_lang, #llm_target_lang, #llm_title_target_lang, #llm_story_plot, #llm_story_intro, #llm_story_finale, #llm_story_card_text, #llm_story_cefr_level, #llm_story_grammar_topics, #title' )
			.addClass( 'llm-import-field-updated' );
		window.setTimeout( function () {
			$( '.llm-import-field-updated' ).removeClass( 'llm-import-field-updated' );
		}, 2500 );

		var $box = $( '#llm_story_settings' );
		if ( $box.length ) {
			$( 'html, body' ).animate( { scrollTop: $box.offset().top - 60 }, 400 );
		}
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

	} ); // fine DOMReady principale

	// ============================================================
	// Importazione storia completa — DOMReady separato (no early return)
	// ============================================================

	$( function () {

	var llmFullImportToken = '';

	var META_LABELS = {
		TITOLO:              'Titolo',
		LINGUA_INTERFACCIA:  'Lingua interfaccia',
		LINGUA_OBIETTIVO:    'Lingua obiettivo',
		TITOLO_OBIETTIVO:    'Titolo (lingua obiettivo)',
		TRAMA:               'Trama',
		INTRODUZIONE:        'Introduzione',
		FINALE:              'Finale',
		SCHEDA:              'Breve testo scheda',
		CATEGORIA:           'Categoria',
		LIVELLO:             'Livello CEFR',
		LIVELLO_CEFR:        'Livello CEFR',
		GRAMMATICA:          'Topic Grammaticali',
		TOPIC_GRAMMATICALI:  'Topic Grammaticali',
	};

	function llmFullImportShowModal( show ) {
		var $m = $( '#llm-full-import-modal' );
		if ( ! $m.length ) { return; }
		$m.prop( 'hidden', ! show );
		$m.attr( 'aria-hidden', show ? 'false' : 'true' );
		if ( show ) {
			$( document.body ).addClass( 'llm-csv-modal-open' );
		} else {
			$( document.body ).removeClass( 'llm-csv-modal-open' );
		}
	}

	function llmFullImportReset() {
		$( '#llm-full-import-meta-rows' ).empty();
		$( '#llm-full-import-phrases-rows' ).empty();
		$( '#llm-full-import-phrases-summary' ).text( '' );
		$( '#llm-full-import-phrases-wrap' ).prop( 'hidden', true );
		$( '#llm-full-import-warnings' ).empty().prop( 'hidden', true );
		$( '#llm-full-import-log' ).text( '' );
		$( '#llm-full-import-step-paste' ).prop( 'hidden', true );
		$( '#llm-full-import-step-preview' ).prop( 'hidden', false );
		$( '#llm-full-import-step-log' ).prop( 'hidden', true );
		$( '#llm-full-import-foot-paste' ).prop( 'hidden', true );
		$( '#llm-full-import-foot-preview' ).prop( 'hidden', false );
		$( '#llm-full-import-foot-done' ).prop( 'hidden', true );
		$( '#llm-full-import-confirm' ).prop( 'disabled', false );
		$( '#llm-full-import-cancel' ).prop( 'disabled', false );
		llmFullImportToken = '';
	}

	function llmFullImportOpenPasteMode() {
		llmFullImportReset();
		$( '#llm-full-import-modal-title' ).text(
			( typeof llmAdmin !== 'undefined' && llmAdmin.fullImportPasteTitle )
				? llmAdmin.fullImportPasteTitle
				: 'Importa dati della storia da Story Importer'
		);
		$( '#llm-full-import-step-paste' ).prop( 'hidden', false );
		$( '#llm-full-import-step-preview' ).prop( 'hidden', true );
		$( '#llm-full-import-foot-paste' ).prop( 'hidden', false );
		$( '#llm-full-import-foot-preview' ).prop( 'hidden', true );
		var demo = ( typeof llmAdmin !== 'undefined' && llmAdmin.fullImportDemoContent ) ? llmAdmin.fullImportDemoContent : '';
		$( '#llm-full-import-paste-text' ).val( demo );
		llmFullImportShowModal( true );
		window.setTimeout( function () {
			$( '#llm-full-import-paste-text' ).trigger( 'focus' );
		}, 100 );
	}

	function llmFullImportEnsurePostId() {
		if ( typeof llmAdmin === 'undefined' || ! llmAdmin.postId ) {
			alert( ( llmAdmin && llmAdmin.fullImportNeedSave ) ? llmAdmin.fullImportNeedSave : 'Salva prima la bozza della storia.' );
			return false;
		}
		return true;
	}

	function llmFullImportAppendLog( lines, done ) {
		var $log = $( '#llm-full-import-log' );
		$log.text( '' );
		var i = 0;
		function step() {
			if ( i >= lines.length ) {
				if ( typeof done === 'function' ) { done(); }
				return;
			}
			$log.append( ( i > 0 ? '\n' : '' ) + lines[ i ] );
			i += 1;
			window.setTimeout( step, 200 );
		}
		step();
	}

	function llmFullImportApplyPreview( data ) {
		var meta     = data.meta || {};
		var $tbody   = $( '#llm-full-import-meta-rows' );
		$tbody.empty();

		var keys = [ 'TITOLO', 'LINGUA_INTERFACCIA', 'LINGUA_OBIETTIVO', 'TITOLO_OBIETTIVO', 'TRAMA', 'INTRODUZIONE', 'FINALE', 'SCHEDA', 'CATEGORIA', 'LIVELLO', 'LIVELLO_CEFR', 'GRAMMATICA', 'TOPIC_GRAMMATICALI' ];
		keys.forEach( function ( k ) {
			if ( ! ( k in meta ) ) { return; }
			var label = META_LABELS[ k ] || k;
			var val   = meta[ k ] || '';
			var short = val.length > 200 ? val.slice( 0, 197 ) + '…' : val;
			var $tr   = $( '<tr/>' );
			$tr.append( $( '<th style="width:180px;vertical-align:top"/>' ).text( label ) );
			$tr.append( $( '<td class="llm-csv-cell-text" style="white-space:pre-wrap"/>' ).text( short || '—' ) );
			$tbody.append( $tr );
		} );

		var phCount = data.phrases_count || 0;
		$( '#llm-full-import-phrases-summary' ).text(
			phCount > 0
				? phCount + ' frasi trovate nel file (anteprima prime ' + Math.min( phCount, 5 ) + ').'
				: 'Nessuna frase nel file.'
		);

		var prev = data.phrases_preview || [];
		if ( prev.length > 0 ) {
			var $ptbody = $( '#llm-full-import-phrases-rows' );
			$ptbody.empty();
			prev.forEach( function ( row ) {
				var $tr = $( '<tr/>' );
				$tr.append( $( '<td/>' ).text( String( row.position ) ) );
				$tr.append( $( '<td/>' ).append( $( '<span class="llm-csv-badge llm-csv-badge--' + row.action + '"/>' ).text( row.action === 'replace' ? 'Sost.' : 'Add.' ) ) );
				$tr.append( $( '<td class="llm-csv-cell-text"/>' ).text( ( row.interface || '' ).slice( 0, 80 ) ) );
				$tr.append( $( '<td class="llm-csv-cell-text"/>' ).text( ( row.target || '' ).slice( 0, 80 ) ) );
				$ptbody.append( $tr );
			} );
			$( '#llm-full-import-phrases-wrap' ).prop( 'hidden', false );
		}

		var warnings = data.warnings || [];
		var $warnList = $( '#llm-full-import-warnings' );
		if ( warnings.length > 0 ) {
			$warnList.empty();
			warnings.forEach( function ( w ) { $warnList.append( $( '<li/>' ).text( w ) ); } );
			$warnList.prop( 'hidden', false );
		}

		llmFullImportToken = data.token || '';
	}

	function llmFullImportRunPreview( fd ) {
		llmFullImportReset();
		$( '#llm-full-import-modal-title' ).text(
			( typeof llmAdmin !== 'undefined' && llmAdmin.fullImportModalTitle )
				? llmAdmin.fullImportModalTitle
				: 'Anteprima importazione storia'
		);
		$( '#llm-full-import-phrases-summary' ).text( llmAdmin.fullImportLoading || 'Lettura file…' );
		llmFullImportShowModal( true );

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
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( llmAdmin.fullImportErrGeneric || 'Errore' );
					alert( msg );
					llmFullImportShowModal( false );
					return;
				}
				llmFullImportApplyPreview( res.data );
			} )
			.fail( function ( xhr ) {
				var msg = llmAdmin.fullImportErrGeneric || 'Errore';
				if ( xhr.responseJSON && xhr.responseJSON.data ) {
					if ( xhr.responseJSON.data.message ) { msg = xhr.responseJSON.data.message; }
					var errs = xhr.responseJSON.data.errors;
					if ( errs && errs.length ) { msg = errs.join( '\n' ); }
				}
				alert( msg );
				llmFullImportShowModal( false );
			} );
	}

	function llmFullImportBuildFd() {
		var fd = new FormData();
		fd.append( 'action',     llmAdmin.fullImportPreviewAction || 'llm_story_full_import_preview' );
		fd.append( 'nonce',      llmAdmin.fullImportNonce || '' );
		fd.append( 'nonce_post', llmAdmin.fullImportNoncePost || '' );
		fd.append( 'post_id',    String( llmAdmin.postId || 0 ) );
		return fd;
	}

	if ( $( '#llm-full-import-modal' ).length ) {

		// Pulsante "Incolla csv" — apre il modal con textarea
		$( document ).on( 'click', '#llm-full-import-incolla-csv', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			if ( ! llmFullImportEnsurePostId() ) {
				return;
			}
			llmFullImportOpenPasteMode();
		} );

		// Anteprima da testo incollato (dentro il modal)
		$( document ).on( 'click', '#llm-full-import-paste-preview-btn', function ( e ) {
			e.preventDefault();
			var text = ( $( '#llm-full-import-paste-text' ).val() || '' ).trim();
			if ( ! text ) {
				alert( 'Incolla prima il contenuto del file.' );
				return;
			}
			$( '#llm-full-import-step-paste' ).prop( 'hidden', true );
			$( '#llm-full-import-foot-paste' ).prop( 'hidden', true );
			$( '#llm-full-import-phrases-summary' ).text( llmAdmin.fullImportLoading || 'Lettura file…' );
			$( '#llm-full-import-step-preview' ).prop( 'hidden', false );
			$( '#llm-full-import-foot-preview' ).prop( 'hidden', false );
			var fd = llmFullImportBuildFd();
			fd.append( 'text_content', text );
			llmFullImportRunPreview( fd );
		} );

		// Annulla da step paste
		$( document ).on( 'click', '#llm-full-import-paste-cancel', function ( e ) {
			e.preventDefault();
			llmFullImportReset();
			llmFullImportShowModal( false );
		} );

		$( '#llm-full-import-cancel, #llm-full-import-modal-close, #llm-full-import-modal .llm-csv-modal__backdrop' ).on( 'click', function ( e ) {
			e.preventDefault();
			llmFullImportReset();
			llmFullImportShowModal( false );
		} );

		$( '#llm-full-import-confirm' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! llmFullImportToken ) { return; }

			$( '#llm-full-import-confirm' ).prop( 'disabled', true );
			$( '#llm-full-import-cancel' ).prop( 'disabled', true );
			$( '#llm-full-import-step-preview' ).prop( 'hidden', true );
			$( '#llm-full-import-step-log' ).prop( 'hidden', false );
			$( '#llm-full-import-foot-preview' ).prop( 'hidden', true );

			$.ajax( {
				url:      llmAdmin.ajaxUrl,
				type:     'POST',
				dataType: 'json',
				data: {
					action:     llmAdmin.fullImportCommitAction || 'llm_story_full_import_commit',
					nonce:      llmAdmin.fullImportNonce || '',
					nonce_post: llmAdmin.fullImportNoncePost || '',
					post_id:    llmAdmin.postId,
					token:      llmFullImportToken,
				},
			} )
				.done( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						var msg = ( res && res.data && res.data.message ) ? res.data.message : ( llmAdmin.fullImportErrGeneric || 'Errore' );
						alert( msg );
						llmFullImportReset();
						llmFullImportShowModal( false );
						return;
					}
					if ( res.data.form ) {
						llmApplyImportedStoryToForm( res.data.form, res.data.phrases );
					}
					llmFullImportAppendLog( res.data.log || [], function () {
						llmFullImportToken = '';
						$( '#llm-full-import-foot-done' ).prop( 'hidden', false );
					} );
				} )
				.fail( function ( xhr ) {
					var msg = llmAdmin.fullImportErrGeneric || 'Errore';
					if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						msg = xhr.responseJSON.data.message;
					}
					alert( msg );
					llmFullImportReset();
					llmFullImportShowModal( false );
				} );
		} );

		$( '#llm-full-import-done' ).on( 'click', function ( e ) {
			e.preventDefault();
			llmFullImportReset();
			llmFullImportShowModal( false );
		} );
	}

	} ); // fine DOMReady full import

}( jQuery ) );
