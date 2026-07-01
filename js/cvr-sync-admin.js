/* globals DianalundCVR, jQuery */
/* CVR Sync Admin – vanilla JS / jQuery, no external frameworks */

jQuery( document ).ready( function ( $ ) {
    'use strict';

    var cfg     = DianalundCVR || {};
    var strings = cfg.strings  || {};

    // ----------------------------------------------------------------
    // Test connection button (settings page)
    // ----------------------------------------------------------------

    $( '#dianalund-cvr-test-btn' ).on( 'click', function () {
        var $btn     = $( this );
        var $spinner = $( '#dianalund-cvr-test-spinner' );
        var $result  = $( '#dianalund-cvr-test-result' );

        $btn.prop( 'disabled', true );
        $spinner.css( 'visibility', 'visible' );
        $result.html( '' );

        $.post( cfg.ajax_url, {
            action : 'dianalund_cvr_test_connection',
            nonce  : cfg.nonce
        } )
        .done( function ( response ) {
            var type = response.success ? 'success' : 'error';
            var msg  = response.data && response.data.message ? response.data.message : strings.network_error;
            $result.html( '<div class="notice notice-' + type + ' inline" style="margin:0"><p>' + msg + '</p></div>' );
        } )
        .fail( function () {
            $result.html( '<div class="notice notice-error inline" style="margin:0"><p>' + strings.network_error + '</p></div>' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.css( 'visibility', 'hidden' );
        } );
    } );

    // ----------------------------------------------------------------
    // Compare page – bail out when not present
    // ----------------------------------------------------------------

    if ( $( '#dianalund-cvr-compare-page' ).length === 0 ) {
        return;
    }

    var localPosts = cfg.local_posts || [];
    var currentCpt = cfg.current_cpt || 'erhvervsside';
    var currentPage = 1;
    var pendingLinkPostId = 0;

    $( '#dianalund-cvr-auto-link-btn' ).on( 'click', function () {
        if ( ! window.confirm( strings.confirm_auto_link ) ) {
            return;
        }

        var $btn = $( this );
        var $spinner = $( '#dianalund-cvr-auto-link-spinner' );
        var $result = $( '#dianalund-cvr-auto-link-result' );

        $btn.prop( 'disabled', true );
        $spinner.css( 'visibility', 'visible' );
        $result.html( '' );

        $.post( cfg.ajax_url, {
            action: 'dianalund_cvr_auto_link_cpt',
            nonce: cfg.nonce,
            post_type: currentCpt
        } )
        .done( function ( response ) {
            if ( ! response.success ) {
                var err = response.data && response.data.message ? response.data.message : strings.network_error;
                $result.html( '<div class="notice notice-error inline" style="margin:0"><p>' + escHtml( err ) + '</p></div>' );
                return;
            }

            var data = response.data || {};
            var stats = data.stats || {};
            var summary = data.message || 'Auto-linkning gennemført.';
            summary += ' (Linket: ' + ( stats.linked || 0 )
                + ', Allerede linket: ' + ( stats.already_linked || 0 )
                + ', Ingen match: ' + ( stats.no_match || 0 )
                + ', Fejl: ' + ( stats.errors || 0 ) + ')';

            $result.html( '<div class="notice notice-success inline" style="margin:0"><p>' + escHtml( summary ) + '</p></div>' );

            var linkedRows = data.linked_rows || [];
            for ( var i = 0; i < linkedRows.length; i++ ) {
                var item = linkedRows[ i ];
                var postId = item.post_id;
                var cvrNum = item.cvr_nummer;
                var $row = $( '#dianalund-local-list tr[data-post-id="' + postId + '"]' );

                if ( ! $row.length ) {
                    continue;
                }

                $row.attr( 'data-cvr', cvrNum );
                $row.find( '.local-col-cvr' ).html( escHtml( cvrNum ) );
                $row.find( '.cvr-badge' )
                    .removeClass( 'cvr-badge--unlinked cvr-badge--synced' )
                    .addClass( 'cvr-badge--linked' )
                    .text( 'Linket' );

                if ( ! $row.find( '.local-action-update' ).length ) {
                    $row.find( '.local-col-actions' ).append(
                        ' <button type="button" class="button button-small local-action-update"'
                        + ' data-post-id="' + postId + '"'
                        + ' data-cvr="' + escAttr( cvrNum ) + '">Opdatér fra CVR</button>'
                        + ' <button type="button" class="button button-small button-link-delete local-action-unlink"'
                        + ' data-post-id="' + postId + '">Fjern link</button>'
                    );
                } else {
                    $row.find( '.local-action-update' ).attr( 'data-cvr', cvrNum );
                }

                for ( var j = 0; j < localPosts.length; j++ ) {
                    if ( localPosts[ j ].post_id === parseInt( postId, 10 ) ) {
                        localPosts[ j ].cvr_nummer = cvrNum;
                        break;
                    }
                }

                localByCvr[ cvrNum ] = postId;
            }

            applyMatchHighlighting();
        } )
        .fail( function () {
            $result.html( '<div class="notice notice-error inline" style="margin:0"><p>' + escHtml( strings.network_error ) + '</p></div>' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
            $spinner.css( 'visibility', 'hidden' );
        } );
    } );

    // Build quick lookup: cvr_nummer -> post_id.
    var localByCvr = {};
    for ( var i = 0; i < localPosts.length; i++ ) {
        var lp = localPosts[ i ];
        if ( lp.cvr_nummer ) {
            localByCvr[ lp.cvr_nummer ] = lp.post_id;
        }
    }

    // ----------------------------------------------------------------
    // CVR search form
    // ----------------------------------------------------------------

    $( '#dianalund-cvr-search-form' ).on( 'submit', function ( e ) {
        e.preventDefault();
        currentPage = 1;
        loadCVRList( currentPage );
    } );

    $( '#dianalund-cvr-prev-page' ).on( 'click', function () {
        if ( currentPage <= 1 ) {
            return;
        }
        loadCVRList( currentPage - 1 );
    } );

    $( '#dianalund-cvr-next-page' ).on( 'click', function () {
        loadCVRList( currentPage + 1 );
    } );

    function loadCVRList( page ) {
        var requestedPage = parseInt( page, 10 ) || 1;
        var search        = $( '#dianalund-cvr-search-input' ).val();
        var postnr        = ( $( '#dianalund-cvr-postnr-input' ).val() || '' ).replace( /\D+/g, '' );
        var onlyActive    = $( '#dianalund-cvr-only-active' ).is( ':checked' );
        var $spinner = $( '#dianalund-cvr-search-spinner' );
        var $list    = $( '#dianalund-cvr-list' );

        $( '#dianalund-cvr-postnr-input' ).val( postnr );

        $spinner.css( 'visibility', 'visible' );
        $list.html( '<tr><td colspan="4">' + escHtml( strings.loading ) + '</td></tr>' );

        $.post( cfg.ajax_url, {
            action : 'dianalund_cvr_search',
            nonce  : cfg.nonce,
            search : search,
            postnr : postnr,
            page   : requestedPage,
            only_active: onlyActive ? '1' : '0'
        } )
        .done( function ( response ) {
            if ( ! response.success ) {
                var errMsg = response.data && response.data.message ? response.data.message : strings.network_error;
                $list.html( '<tr><td colspan="4" class="cvr-notice cvr-notice--error">' + escHtml( errMsg ) + '</td></tr>' );
                updatePager( 1, 1, false, false );
                return;
            }

            var data = response.data;
            currentPage = data.page || requestedPage;
            renderCVRList( data.companies, data.shown, data.total );
            updatePager( data.page, data.pages, data.has_prev, data.has_next );
            applyMatchHighlighting();
        } )
        .fail( function () {
            $list.html( '<tr><td colspan="4" class="cvr-notice cvr-notice--error">' + escHtml( strings.network_error ) + '</td></tr>' );
            updatePager( 1, 1, false, false );
        } )
        .always( function () {
            $spinner.css( 'visibility', 'hidden' );
        } );
    }

    function updatePager( page, pages, hasPrev, hasNext ) {
        var safePage = parseInt( page, 10 ) || 1;
        var safePages = parseInt( pages, 10 ) || 1;

        $( '#dianalund-cvr-pager' ).show();
        $( '#dianalund-cvr-page-meta' ).text( 'Side ' + safePage + ' af ' + safePages );
        $( '#dianalund-cvr-prev-page' ).prop( 'disabled', ! hasPrev );
        $( '#dianalund-cvr-next-page' ).prop( 'disabled', ! hasNext );
    }

    function renderCVRList( companies, shown, total ) {
        var $list    = $( '#dianalund-cvr-list' );
        var $wrap    = $( '#dianalund-cvr-count-wrap' );
        var $cShown  = $( '#dianalund-cvr-count-shown' );
        var $cTotal  = $( '#dianalund-cvr-count-total' );

        $cShown.text( shown );
        $cTotal.text( total );
        $wrap.show();

        if ( ! companies || ! companies.length ) {
            $list.html( '<tr><td colspan="4">' + escHtml( strings.no_cvr ) + '</td></tr>' );
            return;
        }

        var rows = '';

        for ( var i = 0; i < companies.length; i++ ) {
            var c       = companies[ i ];
            var matchId = localByCvr[ c.cvr_nummer ] || null;
            var rowCls  = 'cvr-row' + ( matchId ? ' cvr-row--matched' : '' );
            var matchAttr = matchId ? ' data-matched-post="' + matchId + '"' : '';
            var branchLabel = '';

            if ( c.branche_kode && c.branche_tekst ) {
                branchLabel = c.branche_kode + ' ' + c.branche_tekst;
            } else if ( c.branche_tekst ) {
                branchLabel = c.branche_tekst;
            }

            rows += '<tr class="' + rowCls + '"' + matchAttr + ' data-cvr="' + escAttr( c.cvr_nummer ) + '">';
            rows += '<td class="cvr-col-navn"><strong>' + escHtml( c.navn ) + '</strong>';
            if ( branchLabel ) {
                rows += '<br><small>' + escHtml( branchLabel ) + '</small>';
            }
            rows += '<br><button type="button" class="button-link cvr-action-toggle-details">Vis detaljer</button>';
            rows += '</td>';
            rows += '<td class="cvr-col-cvr">' + escHtml( c.cvr_nummer ) + '</td>';

            var byStr = c.postnr ? c.postnr + ' ' + c.by : c.by;
            rows += '<td class="cvr-col-by">' + escHtml( byStr ) + '</td>';
            rows += '<td class="cvr-col-actions">';

            if ( matchId ) {
                rows += '<button type="button" class="button button-small cvr-action-update"'
                      + ' data-post-id="' + matchId + '"'
                      + ' data-cvr="' + escAttr( c.cvr_nummer ) + '">'
                      + 'Opdatér lokal post'
                      + '</button>';
            } else {
                if ( pendingLinkPostId ) {
                    rows += '<button type="button" class="button button-small button-primary cvr-action-link-pending"'
                          + ' data-post-id="' + pendingLinkPostId + '"'
                          + ' data-cvr="' + escAttr( c.cvr_nummer ) + '">'
                          + 'Knyt til valgt lokal post'
                          + '</button> ';
                }
                rows += '<button type="button" class="button button-small cvr-action-create"'
                      + ' data-cvr="' + escAttr( c.cvr_nummer ) + '">'
                      + 'Opret ny post'
                      + '</button>';
                rows += ' <button type="button" class="button button-small cvr-action-link-toggle"'
                      + ' data-cvr="' + escAttr( c.cvr_nummer ) + '">'
                      + 'Knyt til...'
                      + '</button>';
                rows += '<div class="cvr-link-select-wrap" style="display:none;">'
                      + buildLocalPostSelect()
                      + '</div>';
            }

            rows += '</td>';
            rows += '</tr>';

            rows += '<tr class="cvr-row-details" style="display:none;" data-cvr="' + escAttr( c.cvr_nummer ) + '">';
            rows += '<td colspan="4">';
            rows += '<div class="cvr-details-grid">';
            rows += '<p><strong>Status:</strong> ' + escHtml( c.status || '—' ) + '</p>';
            rows += '<p><strong>Virksomhedsform:</strong> ' + escHtml( c.virksomhedsform || '—' ) + '</p>';
            rows += '<p><strong>Adresse:</strong> ' + escHtml( c.adresse || '—' ) + '</p>';
            rows += '<p><strong>Postnr / By:</strong> ' + escHtml( ( c.postnr ? c.postnr : '' ) + ( c.by ? ' ' + c.by : '' ) || '—' ) + '</p>';
            rows += '<p><strong>Telefon:</strong> ' + escHtml( c.telefon || '—' ) + '</p>';
            rows += '<p><strong>Email:</strong> ' + escHtml( c.email || '—' ) + '</p>';
            rows += '<p><strong>Website:</strong> ' + escHtml( c.website || '—' ) + '</p>';
            rows += '<p><strong>Ansatte:</strong> ' + escHtml( c.ansatte || '—' ) + '</p>';
            rows += '</div>';
            rows += '</td>';
            rows += '</tr>';
        }

        $list.html( rows );
    }

    function buildLocalPostSelect() {
        var html = '<select class="cvr-local-post-select">'
                 + '<option value="">— vælg lokal post —</option>';
        for ( var i = 0; i < localPosts.length; i++ ) {
            var lp    = localPosts[ i ];
            var label = lp.title + ( lp.cvr_nummer ? ' [CVR: ' + lp.cvr_nummer + ']' : '' );
            html += '<option value="' + lp.post_id + '">' + escHtml( label ) + '</option>';
        }
        html += '</select>';
        html += ' <button type="button" class="button button-primary button-small cvr-action-link-confirm">Gem link</button>';
        html += ' <button type="button" class="button-link cvr-action-link-cancel">Annuller</button>';
        return html;
    }

    function applyMatchHighlighting() {
        var cvrInList = {};
        $( '#dianalund-cvr-list .cvr-row' ).each( function () {
            cvrInList[ $( this ).data( 'cvr' ) ] = true;
        } );

        $( '#dianalund-local-list tr[data-post-id]' ).each( function () {
            var cvrNum = $( this ).data( 'cvr' );
            if ( cvrNum && cvrInList[ cvrNum ] ) {
                $( this ).addClass( 'local-row--matched' );
            } else {
                $( this ).removeClass( 'local-row--matched' );
            }
        } );
    }

    // ----------------------------------------------------------------
    // CVR list actions (event delegation)
    // ----------------------------------------------------------------

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-update', function () {
        if ( ! window.confirm( strings.confirm_update ) ) {
            return;
        }
        var $btn   = $( this );
        var postId = $btn.data( 'post-id' );
        var cvrNum = $btn.data( 'cvr' );

        doAjaxAction( $btn, 'dianalund_cvr_update_post', { post_id: postId, cvr_nummer: cvrNum }, function ( data ) {
            $btn.closest( 'tr' ).addClass( 'cvr-row--updated' );
            // Refresh name in local list.
            if ( data.navn ) {
                $( '#dianalund-local-list tr[data-post-id="' + postId + '"] .local-col-navn a' ).text( data.navn );
            }
        } );
    } );

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-create', function () {
        if ( ! window.confirm( strings.confirm_create ) ) {
            return;
        }
        var $btn   = $( this );
        var cvrNum = $btn.data( 'cvr' );

        doAjaxAction( $btn, 'dianalund_cvr_create_post', { cvr_nummer: cvrNum, post_type: currentCpt }, function ( data ) {
            $btn.prop( 'disabled', true ).text( 'Oprettet' );
            if ( data.edit_url ) {
                $btn.after( ' <a href="' + escAttr( data.edit_url ) + '" target="_blank" class="button button-small">Redigér</a>' );
            }
        } );
    } );

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-link-toggle', function () {
        $( this ).closest( 'tr' ).find( '.cvr-link-select-wrap' ).slideToggle( 150 );
    } );

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-link-confirm', function () {
        var $row   = $( this ).closest( 'tr' );
        var cvrNum = $row.data( 'cvr' );
        var postId = $row.find( '.cvr-local-post-select' ).val();

        if ( ! postId ) {
            return;
        }

        var $btn = $( this );

        doAjaxAction( $btn, 'dianalund_cvr_link_post', { post_id: postId, cvr_nummer: cvrNum }, function () {
            $row.find( '.cvr-link-select-wrap' ).slideUp( 150 );

            // Update local data arrays.
            for ( var i = 0; i < localPosts.length; i++ ) {
                if ( localPosts[ i ].post_id === parseInt( postId, 10 ) ) {
                    if ( localPosts[ i ].cvr_nummer ) {
                        delete localByCvr[ localPosts[ i ].cvr_nummer ];
                    }
                    localPosts[ i ].cvr_nummer = cvrNum;
                    localByCvr[ cvrNum ] = parseInt( postId, 10 );
                    break;
                }
            }

            // Update local row display.
            var $localRow = $( '#dianalund-local-list tr[data-post-id="' + postId + '"]' );
            $localRow.attr( 'data-cvr', cvrNum );
            $localRow.find( '.local-col-cvr' ).html( escHtml( cvrNum ) );

            // Show action buttons on local row if not already present.
            if ( ! $localRow.find( '.local-action-update' ).length ) {
                $localRow.find( '.local-col-actions' ).html(
                    '<button type="button" class="button button-small local-action-find-cvr"'
                    + ' data-post-id="' + postId + '"'
                    + ' data-search="' + escAttr( $localRow.find( '.local-col-navn a' ).text() ) + '">Find i CVR</button> '
                    + '<button type="button" class="button button-small button-link-delete local-action-delete-post"'
                    + ' data-post-id="' + postId + '">Slet</button> '
                    + '<button type="button" class="button button-small local-action-update"'
                    + ' data-post-id="' + postId + '"'
                    + ' data-cvr="' + escAttr( cvrNum ) + '">Opdatér fra CVR</button>'
                    + ' <button type="button" class="button button-small button-link-delete local-action-unlink"'
                    + ' data-post-id="' + postId + '">Fjern link</button>'
                );
            }

            // Swap CVR row buttons.
            $row.find( '.cvr-action-create' ).remove();
            $row.find( '.cvr-action-link-toggle' ).remove();
            $row.find( '.cvr-link-select-wrap' ).remove();
            $row.find( '.cvr-col-actions' ).prepend(
                '<button type="button" class="button button-small cvr-action-update"'
                + ' data-post-id="' + postId + '"'
                + ' data-cvr="' + escAttr( cvrNum ) + '">Opdatér lokal post</button> '
            );
            $row.addClass( 'cvr-row--matched' );

            applyMatchHighlighting();
        } );
    } );

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-link-cancel', function () {
        $( this ).closest( '.cvr-link-select-wrap' ).slideUp( 150 );
    } );

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-toggle-details', function () {
        var $row = $( this ).closest( 'tr' );
        var cvrNum = $row.data( 'cvr' );
        var $details = $( '#dianalund-cvr-list tr.cvr-row-details[data-cvr="' + cvrNum + '"]' );

        if ( $details.is( ':visible' ) ) {
            $details.hide();
            $( this ).text( 'Vis detaljer' );
            return;
        }

        $details.show();
        $( this ).text( 'Skjul detaljer' );
    } );

    $( '#dianalund-cvr-list' ).on( 'click', '.cvr-action-link-pending', function () {
        var $btn = $( this );
        var postId = $btn.data( 'post-id' );
        var cvrNum = $btn.data( 'cvr' );

        doAjaxAction( $btn, 'dianalund_cvr_link_post', { post_id: postId, cvr_nummer: cvrNum }, function () {
            var $localRow = $( '#dianalund-local-list tr[data-post-id="' + postId + '"]' );
            $localRow.attr( 'data-cvr', cvrNum );
            $localRow.find( '.local-col-cvr' ).html( escHtml( cvrNum ) );
            $localRow.find( '.local-col-actions' ).html(
                '<button type="button" class="button button-small local-action-find-cvr"'
                + ' data-post-id="' + postId + '" data-search="' + escAttr( $localRow.find( '.local-col-navn a' ).text() ) + '">Find i CVR</button> '
                + '<button type="button" class="button button-small button-link-delete local-action-delete-post" data-post-id="' + postId + '">Slet</button> '
                + '<button type="button" class="button button-small local-action-update" data-post-id="' + postId + '" data-cvr="' + escAttr( cvrNum ) + '">Opdatér fra CVR</button> '
                + '<button type="button" class="button button-small button-link-delete local-action-unlink" data-post-id="' + postId + '">Fjern link</button>'
            );
            $localRow.find( '.cvr-badge' )
                .removeClass( 'cvr-badge--unlinked cvr-badge--synced' )
                .addClass( 'cvr-badge--linked' )
                .text( 'Linket' );

            for ( var i = 0; i < localPosts.length; i++ ) {
                if ( localPosts[ i ].post_id === parseInt( postId, 10 ) ) {
                    if ( localPosts[ i ].cvr_nummer ) {
                        delete localByCvr[ localPosts[ i ].cvr_nummer ];
                    }
                    localPosts[ i ].cvr_nummer = cvrNum;
                    localByCvr[ cvrNum ] = parseInt( postId, 10 );
                    break;
                }
            }

            pendingLinkPostId = 0;
            loadCVRList( currentPage );
        } );
    } );

    // ----------------------------------------------------------------
    // Local list actions (event delegation)
    // ----------------------------------------------------------------

    $( '#dianalund-local-list' ).on( 'click', '.local-action-update', function () {
        if ( ! window.confirm( strings.confirm_update ) ) {
            return;
        }
        var $btn   = $( this );
        var postId = $btn.data( 'post-id' );
        var cvrNum = $btn.data( 'cvr' );

        doAjaxAction( $btn, 'dianalund_cvr_update_post', { post_id: postId, cvr_nummer: cvrNum }, function ( data ) {
            $btn.closest( 'tr' ).addClass( 'local-row--updated' );
            if ( data.navn ) {
                $btn.closest( 'tr' ).find( '.local-col-navn a' ).text( data.navn );
                // Also update badge to synced.
                $btn.closest( 'tr' ).find( '.cvr-badge' )
                    .removeClass( 'cvr-badge--linked cvr-badge--unlinked' )
                    .addClass( 'cvr-badge--synced' )
                    .text( 'Synkroniseret' );
            }
        } );
    } );

    $( '#dianalund-local-list' ).on( 'click', '.local-action-unlink', function () {
        if ( ! window.confirm( strings.confirm_unlink ) ) {
            return;
        }
        var $btn   = $( this );
        var postId = $btn.data( 'post-id' );

        doAjaxAction( $btn, 'dianalund_cvr_unlink_post', { post_id: postId }, function () {
            var $row   = $btn.closest( 'tr' );
            var cvrNum = $row.data( 'cvr' );

            // Update local data.
            for ( var i = 0; i < localPosts.length; i++ ) {
                if ( localPosts[ i ].post_id === parseInt( postId, 10 ) ) {
                    delete localByCvr[ localPosts[ i ].cvr_nummer ];
                    localPosts[ i ].cvr_nummer = '';
                    break;
                }
            }

            $row.removeAttr( 'data-cvr' );
            $row.find( '.local-col-cvr' ).html( '<span class="cvr-empty">—</span>' );
            $row.find( '.local-col-actions' ).html(
                '<button type="button" class="button button-small local-action-find-cvr"'
                + ' data-post-id="' + postId + '"'
                + ' data-search="' + escAttr( $row.find( '.local-col-navn a' ).text() ) + '">Find i CVR</button> '
                + '<button type="button" class="button button-small button-link-delete local-action-delete-post"'
                + ' data-post-id="' + postId + '">Slet</button>'
            );
            $row.find( '.cvr-badge' )
                .removeClass( 'cvr-badge--linked cvr-badge--synced' )
                .addClass( 'cvr-badge--unlinked' )
                .text( 'Ikke linket' );
            $row.removeClass( 'local-row--matched local-row--updated' );

            applyMatchHighlighting();
        } );
    } );

    $( '#dianalund-local-list' ).on( 'click', '.local-action-find-cvr', function () {
        var $btn = $( this );
        var postId = parseInt( $btn.data( 'post-id' ), 10 ) || 0;
        var search = $btn.data( 'search' ) || '';

        pendingLinkPostId = postId;
        $( '#dianalund-cvr-search-input' ).val( search );
        $( '#dianalund-cvr-only-active' ).prop( 'checked', false );
        currentPage = 1;
        loadCVRList( 1 );
    } );

    $( '#dianalund-local-list' ).on( 'click', '.local-action-delete-post', function () {
        if ( ! window.confirm( strings.confirm_delete ) ) {
            return;
        }

        var $btn = $( this );
        var postId = $btn.data( 'post-id' );

        doAjaxAction( $btn, 'dianalund_cvr_trash_post', { post_id: postId }, function () {
            var $row = $( '#dianalund-local-list tr[data-post-id="' + postId + '"]' );
            var cvrNum = $row.data( 'cvr' );

            if ( cvrNum ) {
                delete localByCvr[ cvrNum ];
            }

            for ( var i = 0; i < localPosts.length; i++ ) {
                if ( localPosts[ i ].post_id === parseInt( postId, 10 ) ) {
                    localPosts.splice( i, 1 );
                    break;
                }
            }

            $row.fadeOut( 200, function () {
                $( this ).remove();
                applyMatchHighlighting();
            } );
        } );
    } );

    // ----------------------------------------------------------------
    // Local list text filter
    // ----------------------------------------------------------------

    $( '#dianalund-local-filter' ).on( 'input', function () {
        var val = $( this ).val().toLowerCase();
        $( '#dianalund-local-list tr[data-post-id]' ).each( function () {
            var text = $( this ).find( '.local-col-navn' ).text().toLowerCase();
            $( this ).toggle( text.indexOf( val ) !== -1 );
        } );
    } );

    // ----------------------------------------------------------------
    // Generic AJAX action helper
    // ----------------------------------------------------------------

    function doAjaxAction( $btn, action, data, onSuccess ) {
        $btn.prop( 'disabled', true ).addClass( 'cvr-btn--loading' );
        removePrevNotice( $btn );

        var payload = $.extend( {}, { action: action, nonce: cfg.nonce }, data );

        $.post( cfg.ajax_url, payload )
        .done( function ( response ) {
            if ( response.success ) {
                showNotice( $btn, response.data.message, 'success' );
                if ( onSuccess ) {
                    onSuccess( response.data );
                }
            } else {
                var msg = response.data && response.data.message ? response.data.message : strings.network_error;
                showNotice( $btn, msg, 'error' );
            }
        } )
        .fail( function () {
            showNotice( $btn, strings.network_error, 'error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).removeClass( 'cvr-btn--loading' );
        } );
    }

    function removePrevNotice( $btn ) {
        $btn.closest( 'td' ).find( '.cvr-inline-notice' ).remove();
    }

    function showNotice( $btn, message, type ) {
        var $n = $( '<span class="cvr-inline-notice cvr-inline-notice--' + type + '">' + escHtml( message ) + '</span>' );
        $btn.closest( 'td' ).append( $n );
        setTimeout( function () {
            $n.fadeOut( 400, function () { $n.remove(); } );
        }, 4500 );
    }

    // ----------------------------------------------------------------
    // Escaping helpers
    // ----------------------------------------------------------------

    function escHtml( str ) {
        var d = document.createElement( 'div' );
        d.appendChild( document.createTextNode( str || '' ) );
        return d.innerHTML;
    }

    function escAttr( str ) {
        return ( str || '' ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
    }

} );
