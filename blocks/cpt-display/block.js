(function(blocks, editor, components, i18n, element, apiFetch) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var useState = element.useState;
    var useEffect = element.useEffect;
    
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = editor.InspectorControls;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var Button = components.Button;

    registerBlockType('dianalund-cpt/cpt-display', {
        title: i18n.__('Dianalund CPT Display'),
        description: i18n.__('Vis data fra custom post types'),
        category: 'common',
        icon: 'businessman',
        
        attributes: {
            postId: { type: 'string', default: '' },
            postTitle: { type: 'string', default: '' },
            postType: { type: 'string', default: '' },
            showLogo: { type: 'boolean', default: true },
            showName: { type: 'boolean', default: true },
            showFacebook: { type: 'boolean', default: true },
            showInstagram: { type: 'boolean', default: true },
            showWebsite: { type: 'boolean', default: true },
            showEmail: { type: 'boolean', default: true }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            
            var searchState = useState('');
            var searchTerm = searchState[0];
            var setSearchTerm = searchState[1];
            
            var searchResultsState = useState([]);
            var searchResults = searchResultsState[0];
            var setSearchResults = searchResultsState[1];
            
            var isSearchingState = useState(false);
            var isSearching = isSearchingState[0];
            var setIsSearching = isSearchingState[1];
            
            var searchTimeoutState = useState(null);
            var searchTimeout = searchTimeoutState[0];
            var setSearchTimeout = searchTimeoutState[1];
            
            var postDataState = useState(null);
            var postData = postDataState[0];
            var setPostData = postDataState[1];

            // Debounced search function
            var performSearch = function(term) {
                if (!term || term.length < 2) {
                    setSearchResults([]);
                    return;
                }
                
                setIsSearching(true);
                
                Promise.all([
                    apiFetch({ path: '/wp/v2/butiksside?search=' + encodeURIComponent(term) + '&per_page=10' }),
                    apiFetch({ path: '/wp/v2/foreningsside?search=' + encodeURIComponent(term) + '&per_page=10' }),
                    apiFetch({ path: '/wp/v2/erhvervsside?search=' + encodeURIComponent(term) + '&per_page=10' })
                ]).then(function(results) {
                    var allResults = [];
                    
                    results[0].forEach(function(post) {
                        allResults.push({ 
                            id: post.id, 
                            title: post.title.rendered, 
                            type: 'butiksside',
                            typeLabel: 'Butik'
                        });
                    });
                    results[1].forEach(function(post) {
                        allResults.push({ 
                            id: post.id, 
                            title: post.title.rendered, 
                            type: 'foreningsside',
                            typeLabel: 'Forening'
                        });
                    });
                    results[2].forEach(function(post) {
                        allResults.push({ 
                            id: post.id, 
                            title: post.title.rendered, 
                            type: 'erhvervsside',
                            typeLabel: 'Erhverv'
                        });
                    });
                    
                    // Sorter resultater alfabetisk
                    allResults.sort(function(a, b) {
                        return a.title.localeCompare(b.title);
                    });
                    
                    setSearchResults(allResults);
                    setIsSearching(false);
                }).catch(function(error) {
                    console.error('Search error:', error);
                    setSearchResults([]);
                    setIsSearching(false);
                });
            };
            
            // Handle search input change med debouncing
            var handleSearchChange = function(value) {
                setSearchTerm(value);
                
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                var newTimeout = setTimeout(function() {
                    performSearch(value);
                }, 300);
                
                setSearchTimeout(newTimeout);
            };
            
            // Vælg post fra søgeresultater
            var selectPost = function(post) {
                setAttributes({
                    postId: post.id.toString(),
                    postTitle: post.title,
                    postType: post.type
                });
                setSearchTerm('');
                setSearchResults([]);
            };
            
            // Ryd valg
            var clearSelection = function() {
                setAttributes({
                    postId: '',
                    postTitle: '',
                    postType: ''
                });
                setPostData(null);
            };
            
            // Hent post data når post er valgt
            useEffect(function() {
                if (attributes.postId && attributes.postType) {
                    // Hent post data og logo data parallelt
                    Promise.all([
                        apiFetch({ 
                            path: '/wp/v2/' + attributes.postType + '/' + attributes.postId
                        }),
                        apiFetch({
                            path: '/wp/v2/' + attributes.postType + '/' + attributes.postId + '?context=edit'
                        }).catch(function() {
                            return { meta: {} }; // Fallback hvis edit context fejler
                        })
                    ]).then(function(results) {
                        var postData = results[0];
                        var editData = results[1];
                        
                        console.log('Post data:', postData); // Debug
                        console.log('Edit data:', editData); // Debug
                        
                        // Prøv at få logo ID fra forskellige steder
                        var logoId = null;
                        if (postData['allround-cpt_logo_id']) {
                            logoId = postData['allround-cpt_logo_id'];
                        } else if (editData.meta && editData.meta['allround-cpt_logo_id']) {
                            logoId = editData.meta['allround-cpt_logo_id'];
                        }
                        
                        console.log('Logo ID found:', logoId); // Debug
                        
                        // Hent logo URL hvis der er et logo ID
                        var logoPromise = Promise.resolve(null);
                        if (logoId) {
                            logoPromise = apiFetch({
                                path: '/wp/v2/media/' + logoId
                            }).then(function(mediaData) {
                                console.log('Media data:', mediaData); // Debug
                                var logoUrl = null;
                                if (mediaData.media_details && mediaData.media_details.sizes) {
                                    if (mediaData.media_details.sizes.medium) {
                                        logoUrl = mediaData.media_details.sizes.medium.source_url;
                                    } else if (mediaData.media_details.sizes.thumbnail) {
                                        logoUrl = mediaData.media_details.sizes.thumbnail.source_url;
                                    } else {
                                        logoUrl = mediaData.source_url;
                                    }
                                } else {
                                    logoUrl = mediaData.source_url;
                                }
                                console.log('Final logo URL:', logoUrl); // Debug
                                return logoUrl;
                            }).catch(function(error) {
                                console.error('Error loading logo:', error);
                                return null;
                            });
                        } else {
                            console.log('No logo ID found, post data keys:', Object.keys(postData)); // Debug
                            console.log('Edit data meta keys:', editData.meta ? Object.keys(editData.meta) : 'No meta'); // Debug
                        }
                        
                        logoPromise.then(function(logoUrl) {
                            setPostData({
                                title: postData.title.rendered,
                                link: postData.link,
                                type: attributes.postType,
                                meta: editData.meta || {},
                                logoUrl: logoUrl
                            });
                        });
                        
                    }).catch(function(error) {
                        console.error('Error loading post data:', error);
                        setPostData(null);
                    });
                }
            }, [attributes.postId, attributes.postType]);
            
            // Cleanup timeout on unmount
            useEffect(function() {
                return function() {
                    if (searchTimeout) {
                        clearTimeout(searchTimeout);
                    }
                };
            }, [searchTimeout]);
            
            var inspectorControls = el(InspectorControls, {},
                el(PanelBody, { title: i18n.__('Vælg indlæg') },
                    // Vis valgt post eller søgefelt
                    attributes.postId ? 
                        el('div', {},
                            el('p', { style: { marginBottom: '10px' } },
                                el('strong', {}, attributes.postTitle),
                                el('br', {}),
                                el('small', {}, '(' + (attributes.postType === 'butiksside' ? 'Butik' : 
                                                      attributes.postType === 'foreningsside' ? 'Forening' : 'Erhverv') + ')')
                            ),
                            el(Button, {
                                isSecondary: true,
                                isSmall: true,
                                onClick: clearSelection
                            }, i18n.__('Vælg andet indlæg'))
                        ) :
                        el('div', {},
                            el(TextControl, {
                                label: i18n.__('Søg efter indlæg'),
                                value: searchTerm,
                                onChange: handleSearchChange,
                                placeholder: i18n.__('Skriv mindst 2 karakterer...')
                            }),
                            isSearching && el('p', { style: { fontStyle: 'italic', color: '#666', margin: '5px 0' } }, i18n.__('Søger...')),
                            
                            // Vis søgeresultater
                            searchResults.length > 0 && el('div', { 
                                style: { 
                                    maxHeight: '200px', 
                                    overflowY: 'auto',
                                    border: '1px solid #ddd',
                                    marginTop: '10px',
                                    borderRadius: '4px'
                                }
                            },
                                searchResults.map(function(post) {
                                    return el('div', {
                                        key: post.id,
                                        style: {
                                            padding: '8px 12px',
                                            borderBottom: '1px solid #eee',
                                            cursor: 'pointer',
                                            backgroundColor: '#fff'
                                        },
                                        onClick: function() { selectPost(post); },
                                        onMouseEnter: function(e) {
                                            e.target.style.backgroundColor = '#f0f0f0';
                                        },
                                        onMouseLeave: function(e) {
                                            e.target.style.backgroundColor = '#fff';
                                        }
                                    },
                                        el('div', { style: { fontWeight: 'bold' } }, post.title),
                                        el('div', { style: { fontSize: '12px', color: '#666' } }, post.typeLabel)
                                    );
                                })
                            ),
                            
                            searchTerm && searchTerm.length >= 2 && searchResults.length === 0 && !isSearching && 
                                el('p', { style: { color: '#666', fontStyle: 'italic', marginTop: '10px' } }, 
                                    i18n.__('Ingen resultater fundet'))
                        )
                ),
                
                // Vis indstillinger kun hvis et indlæg er valgt
                attributes.postId && el(PanelBody, { title: i18n.__('Vis indstillinger') },
                    el(ToggleControl, {
                        label: i18n.__('Vis logo'),
                        checked: attributes.showLogo,
                        onChange: function(showLogo) { setAttributes({ showLogo: showLogo }); }
                    }),
                    el(ToggleControl, {
                        label: i18n.__('Vis navn'),
                        checked: attributes.showName,
                        onChange: function(showName) { setAttributes({ showName: showName }); }
                    }),
                    el(ToggleControl, {
                        label: i18n.__('Vis Facebook'),
                        checked: attributes.showFacebook,
                        onChange: function(showFacebook) { setAttributes({ showFacebook: showFacebook }); }
                    }),
                    el(ToggleControl, {
                        label: i18n.__('Vis Instagram'),
                        checked: attributes.showInstagram,
                        onChange: function(showInstagram) { setAttributes({ showInstagram: showInstagram }); }
                    }),
                    el(ToggleControl, {
                        label: i18n.__('Vis hjemmeside'),
                        checked: attributes.showWebsite,
                        onChange: function(showWebsite) { setAttributes({ showWebsite: showWebsite }); }
                    }),
                    el(ToggleControl, {
                        label: i18n.__('Vis email'),
                        checked: attributes.showEmail,
                        onChange: function(showEmail) { setAttributes({ showEmail: showEmail }); }
                    })
                )
            );
            
            var blockContent;
            if (!attributes.postId) {
                blockContent = el('p', { style: { textAlign: 'center', color: '#666', padding: '20px' } }, 
                    i18n.__('Søg og vælg et indlæg i inspector panelet til højre.'));
            } else if (postData) {
                var previewElements = [];
                
                // Logo og navn som link preview
                if (attributes.showLogo || attributes.showName) {
                    var linkElements = [];
                    
                    if (attributes.showLogo) {
                        // Vis faktisk logo hvis det findes, ellers placeholder
                        var logoElement;
                        if (postData.logoUrl) {
                            logoElement = el('img', {
                                src: postData.logoUrl,
                                alt: postData.title + ' logo',
                                style: {
                                    maxWidth: '120px',
                                    maxHeight: '80px',
                                    objectFit: 'contain',
                                    display: 'block',
                                    margin: '0 auto'
                                }
                            });
                        } else {
                            logoElement = el('div', { 
                                style: { 
                                    width: '120px', 
                                    height: '80px', 
                                    backgroundColor: '#f0f0f0', 
                                    display: 'flex', 
                                    alignItems: 'center', 
                                    justifyContent: 'center',
                                    margin: '0 auto',
                                    borderRadius: '4px',
                                    fontSize: '12px',
                                    color: '#666'
                                }
                            }, 'Intet logo');
                        }
                        
                        linkElements.push(
                            el('div', { className: 'cpt-logo', key: 'logo', style: { marginBottom: '10px' } },
                                logoElement
                            )
                        );
                    }
                    
                    if (attributes.showName) {
                        linkElements.push(
                            el('h3', { 
                                className: 'cpt-name', 
                                key: 'name',
                                style: { margin: '10px 0', fontSize: '1.1em', color: '#0073aa' }
                            }, postData.title)
                        );
                    }
                    
                    previewElements.push(
                        el('div', { 
                            key: 'main-link',
                            style: { 
                                marginBottom: '15px',
                                cursor: 'pointer'
                            },
                            title: 'Klikbart link til: ' + postData.title
                        }, 
                            linkElements
                        )
                    );
                }
                
                // Social ikoner preview (med SVG ikoner)
                var socialElements = [];
                if (attributes.showFacebook) {
                    socialElements.push(
                        el('span', { 
                            key: 'fb', 
                            className: 'social-icon facebook', 
                            title: 'Facebook',
                            style: { margin: '0 8px', cursor: 'pointer' }
                        }, 
                            el('div', { 
                                dangerouslySetInnerHTML: { 
                                    __html: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 26 26"><path d="M23.214,2.25H2.786A2.786,2.786,0,0,0,0,5.036V25.464A2.786,2.786,0,0,0,2.786,28.25h7.965V19.411H7.095V15.25h3.656V12.079c0-3.607,2.147-5.6,5.436-5.6a22.15,22.15,0,0,1,3.222.281V10.3H17.595a2.08,2.08,0,0,0-2.346,2.248v2.7h3.992L18.6,19.411H15.249V28.25h7.965A2.786,2.786,0,0,0,26,25.464V5.036A2.786,2.786,0,0,0,23.214,2.25Z" transform="translate(0 -2.25)" fill="#4267B2"/></svg>' 
                                } 
                            })
                        )
                    );
                }
                if (attributes.showInstagram) {
                    socialElements.push(
                        el('span', { 
                            key: 'ig', 
                            className: 'social-icon instagram', 
                            title: 'Instagram',
                            style: { margin: '0 8px', cursor: 'pointer' }
                        },
                            el('div', { 
                                dangerouslySetInnerHTML: { 
                                    __html: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 26 26"><path d="M13,8.572a6.666,6.666,0,1,0,6.665,6.666A6.655,6.655,0,0,0,13,8.572Zm0,11a4.334,4.334,0,1,1,4.333-4.334A4.341,4.341,0,0,1,13,19.572ZM21.489,8.3a1.554,1.554,0,1,1-1.554-1.555A1.551,1.551,0,0,1,21.489,8.3ZM25.9,9.877a7.7,7.7,0,0,0-2.1-5.448,7.742,7.742,0,0,0-5.447-2.1c-2.146-.122-8.579-.122-10.725,0A7.731,7.731,0,0,0,2.186,4.423a7.721,7.721,0,0,0-2.1,5.448c-.122,2.147-.122,8.581,0,10.727a7.7,7.7,0,0,0,2.1,5.448,7.752,7.752,0,0,0,5.447,2.1c2.146.122,8.579.122,10.725,0a7.692,7.692,0,0,0,5.447-2.1A7.746,7.746,0,0,0,25.9,20.6c.122-2.147.122-8.575,0-10.721ZM23.131,22.9a4.387,4.387,0,0,1-2.471,2.471c-1.711.679-5.771.522-7.662.522s-5.957.151-7.662-.522A4.387,4.387,0,0,1,2.864,22.9c-.679-1.711-.522-5.773-.522-7.664s-.151-5.958.522-7.664A4.387,4.387,0,0,1,5.335,5.1C7.046,4.423,11.107,4.58,13,4.58s5.957-.151,7.662.522a4.387,4.387,0,0,1,2.471,2.471c.679,1.711.522,5.773.522,7.664S23.809,21.2,23.131,22.9Z" transform="translate(0.005 -2.238)" fill="#E4405F"/></svg>' 
                                } 
                            })
                        )
                    );
                }
                if (attributes.showWebsite) {
                    socialElements.push(
                        el('span', { 
                            key: 'web', 
                            className: 'social-icon website', 
                            title: 'Hjemmeside',
                            style: { margin: '0 8px', cursor: 'pointer' }
                        },
                            el('div', { 
                                dangerouslySetInnerHTML: { 
                                    __html: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 29 29"><g transform="translate(-1.5 -1.5)"><path d="M29,16A13,13,0,1,1,16,3,13,13,0,0,1,29,16Z" fill="none" stroke="#333" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/><path d="M3,18H29" transform="translate(0 -2)" fill="none" stroke="#333" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/><path d="M18,3a18.555,18.555,0,0,1,6,13,18.555,18.555,0,0,1-6,13,18.555,18.555,0,0,1-6-13A18.555,18.555,0,0,1,18,3Z" transform="translate(-2)" fill="none" stroke="#333" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/></g></svg>' 
                                } 
                            })
                        )
                    );
                }
                if (attributes.showEmail) {
                    socialElements.push(
                        el('span', { 
                            key: 'email', 
                            className: 'social-icon email', 
                            title: 'Email',
                            style: { margin: '0 8px', cursor: 'pointer' }
                        },
                            el('div', { 
                                dangerouslySetInnerHTML: { 
                                    __html: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="18" viewBox="0 0 34.667 26"><path d="M34.01,13.085a.408.408,0,0,1,.657.318V27.25a3.251,3.251,0,0,1-3.25,3.25H3.25A3.251,3.251,0,0,1,0,27.25V13.41a.4.4,0,0,1,.657-.318c1.517,1.178,3.528,2.674,10.434,7.692,1.429,1.043,3.839,3.236,6.243,3.223,2.417.02,4.875-2.221,6.249-3.223C30.489,15.767,32.493,14.264,34.01,13.085ZM17.333,21.833c1.571.027,3.832-1.977,4.97-2.8,8.985-6.52,9.669-7.089,11.741-8.714a1.62,1.62,0,0,0,.623-1.28V7.75a3.251,3.251,0,0,0-3.25-3.25H3.25A3.251,3.251,0,0,0,0,7.75V9.036a1.63,1.63,0,0,0,.623,1.28c2.072,1.618,2.756,2.194,11.741,8.714C13.5,19.856,15.762,21.86,17.333,21.833Z" transform="translate(0 -4.5)" fill="#D44638"/></svg>' 
                                } 
                            })
                        )
                    );
                }
                
                if (socialElements.length > 0) {
                    previewElements.push(
                        el('div', { 
                            key: 'social',
                            className: 'cpt-social-links',
                            style: { 
                                display: 'flex', 
                                justifyContent: 'center', 
                                gap: '5px',
                                marginTop: '10px'
                            }
                        }, socialElements)
                    );
                }
                
                blockContent = el('div', { 
                    className: 'dianalund-cpt-display',
                    style: {
                        textAlign: 'center',
                        maxWidth: '300px',
                        margin: '0 auto',
                        padding: '20px'
                    }
                }, previewElements);
            } else {
                blockContent = el('p', { style: { textAlign: 'center', color: '#666', padding: '20px' } }, 
                    i18n.__('Indlæser...'));
            }
            
            return el(Fragment, {},
                inspectorControls,
                el('div', { className: 'dianalund-cpt-display-editor' }, blockContent)
            );
        },
        
        save: function() {
            return null; // Server-side rendering
        }
    });
    
})(
    window.wp.blocks,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n,
    window.wp.element,
    window.wp.apiFetch
);
