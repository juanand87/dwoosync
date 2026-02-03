(function( $ ) {
    'use strict';

    $(function() {

        const modalOverlay = $('#wdi-modal-overlay');
        const modalContent = $('#wdi-modal-content');

        // --- FUNCIONES PARA CONTROLAR LA MODAL ---
        function openModal() {
            modalOverlay.css('display', 'flex');
        }
        function closeModal() {
            modalOverlay.hide();
        }

        // --- FUNCIÓN PARA MOSTRAR INDICADOR DE CARGA ---
        function showLoadingIndicator(container, message = 'Cargando...') {
            container.html(`
                <div class="wdi-loading-container">
                    <div class="wdi-loading-spinner">
                        <div class="wdi-spinner-circle"></div>
                    </div>
                    <div class="wdi-loading-text">${message}</div>
                </div>
            `);
        }

        // --- FUNCIÓN PARA OCULTAR INDICADOR DE CARGA ---
        function hideLoadingIndicator(container) {
            container.find('.wdi-loading-container').remove();
        }

        // --- EVENTOS PARA CERRAR LA MODAL ---
        $('#wdi-modal-close').on('click', closeModal);
        modalOverlay.on('click', function(e) {
            // Si se hace clic en el fondo oscuro (overlay) y no en el contenido
            if (e.target === this) {
                closeModal();
            }
        });
        
        let fullSearchResults = []; // Almacena los resultados completos de la última búsqueda
        let lastSearchTerm = ''; // Almacena el último término buscado por texto

        // --- EVENTO PARA TECLA ENTER ---
        $('#discogs_release_id').on('keypress', function(e) {
            if (e.which === 13) { 
                e.preventDefault(); 
                $('#fetch-discogs-data').click(); 
            }
        });

        // --- FUNCIÓN DE BÚSQUEDA PRINCIPAL (se activa con el botón o al cambiar país) ---
        function performSearch(searchTerm, countryFilter = '') {
            const button = $('#fetch-discogs-data');
            const spinner = button.siblings('.spinner');
            const resultsDiv = $('#dwoosync-results');
            const countrySelect = $('#discogs_country_filter');
            const countryWrapper = $('#discogs-country-filter-wrapper');

            openModal();
            spinner.addClass('is-active');
            button.prop('disabled', true);
            countrySelect.prop('disabled', true); // Deshabilitar mientras busca
            
            // Mostrar indicador de carga centrado
            showLoadingIndicator(resultsDiv, 'Buscando en Discogs...');

            const searchData = {
                action: 'wdi_fetch_discogs_data',
                nonce: wdi_secure_ajax.nonce,
                release_id: searchTerm,
                format: $('input[name="discogs_format"]:checked').val(),
                country: countryFilter // Pasar el país seleccionado
            };

            console.log('Enviando petición AJAX:', searchData);
            
            $.post(wdi_secure_ajax.ajax_url, searchData, function(response) {
                console.log('Respuesta recibida:', response);
                
                if (response.success) {
                    const searchResults = response.data.results;
                    
                    // Ocultar indicador de carga
                    hideLoadingIndicator(resultsDiv);
                    
                    // Mostrar warning si está en período de gracia
                    if (response.data.warning) {
                        showWarningInModal(response.data.warning);
                    }
                    
                    // Actualizar la lista de países solo si es una búsqueda inicial (sin filtro de país)
                    if (response.data.country_stats && countryFilter === '') {
                        countrySelect.empty();
                        
                        // Calcular total de ediciones
                        let totalEditions = 0;
                        response.data.country_stats.forEach(function(country) {
                            totalEditions += country.count;
                        });
                        
                        // Siempre mostrar todos los países disponibles
                        countrySelect.append($('<option>').val('').text('All Countries (' + totalEditions + ')'));
                        response.data.country_stats.forEach(function(country) {
                            const optionText = `${country.name} (${country.count} ediciones)`;
                            countrySelect.append($('<option>').val(country.name).text(optionText));
                        });
                        
                        // Mostrar el filtro de países si hay estadísticas
                        countryWrapper.show();
                    } else if (countryFilter !== '') {
                        // Si hay un filtro de país, solo actualizar la selección sin vaciar la lista
                        countrySelect.val(countryFilter);
                    }

                    renderResultsList(searchResults);
                    
                    // --- INICIO: Marcar categorías sugeridas en checkboxes nativos ---
                    if (response.data.suggested_categories && response.data.suggested_categories.length > 0) {
                        markNativeCategoryCheckboxes(response.data.suggested_categories, response.data.selected_format);
                    }
                    // --- FIN: Marcar categorías sugeridas en checkboxes nativos ---

                } else {
                    console.error('Error en la respuesta:', response);
                    // Ocultar indicador de carga en caso de error
                    hideLoadingIndicator(resultsDiv);
                    resultsDiv.html('<p style="color: red;">Error: ' + response.data.message + '</p>');
                    countrySelect.html('<option value="">Primero busca por ID</option>'); // Resetear en caso de error
                    countryWrapper.hide(); // Ocultar el filtro en caso de error
                }
            }).fail(function(xhr, status, error) {
                console.error('Error en petición AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                // Ocultar indicador de carga en caso de error de conexión
                hideLoadingIndicator(resultsDiv);
                
                // Intentar extraer el mensaje específico de la respuesta
                let errorMessage = 'Error de conexión: ' + error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                } catch (e) {
                    // Si no se puede parsear, usar el mensaje genérico
                }
                
                resultsDiv.html('<p style="color: red;">' + errorMessage + '</p>');
            }).always(function() {
                spinner.removeClass('is-active');
                button.prop('disabled', false);
                countrySelect.prop('disabled', false); // Rehabilitar al terminar
            });
        }

        // --- RENDERIZAR LA LISTA DE RESULTADOS (función auxiliar) ---
        function renderResultsList(results) {
            const resultsDiv = $('#dwoosync-results');
            resultsDiv.html('<h4>Resultados (' + results.length + '):</h4>');
            const ul = $('<ul>').css({ 'list-style-type': 'none', 'padding-left': 0 });

            if (!results || results.length === 0) {
                ul.append($('<li>').text('No se encontraron resultados para este filtro.'));
            } else {
                results.forEach(function(item) {
                    const li = $('<li>').addClass('wdi-result-item');
                    const img = $('<img>').attr('src', item.thumb || '').addClass('wdi-thumb');
                    const textDiv = $('<div>').addClass('wdi-info');
                    
                    textDiv.append($('<strong>').text(item.title));
                    textDiv.append($('<br>'));
                    // Obtener traducciones
                    const countryText = wdi_admin_ajax.country_label || 'País:';
                    const yearText = wdi_admin_ajax.year_label || 'Año:';
                    textDiv.append($('<span>').text(`${countryText} ${item.country} | Cat#: ${item.catno} | ${yearText} ${item.year}`));

                    const importButton = $('<button>').attr('type', 'button').addClass('button button-secondary button-small wdi-import-btn').text('Importar').data('release-id', item.id);
                    
                    li.append(img).append(textDiv).append(importButton);
                    ul.append(li);
                });
            }
            resultsDiv.append(ul);
        }

        // --- EVENTO CLICK DEL BOTÓN "BUSCAR" ---
        $('#fetch-discogs-data').on('click', function() {
            const searchTerm = $('#discogs_release_id').val();
            if (!searchTerm) {
                alert('Por favor, introduce un término de búsqueda.');
                return;
            }
            $('#discogs_country_filter').val(''); // Resetea el select de país a "Todos"
            $('#discogs-country-filter-wrapper').hide(); // Ocultar el filtro hasta que se carguen los resultados
            performSearch(searchTerm);
        });
        
        // --- EVENTO CHANGE DEL SELECT DE PAÍS ---
        $('#discogs_country_filter').on('change', function() {
            const searchTerm = $('#discogs_release_id').val();
            if (!searchTerm) {
                // Esto no debería pasar si el select está deshabilitado, pero es una buena práctica
                alert('Realiza una búsqueda principal primero.');
                return;
            }
            const selectedCountry = $(this).val();
            performSearch(searchTerm, selectedCountry);
        });

        // --- LÓGICA DE IMPORTACIÓN ---
        $('#dwoosync-results').on('click', '.wdi-import-btn', function(e) {
            e.preventDefault();

            const importButton = $(this);
            const releaseId = importButton.data('release-id');
            const spinner = $('#fetch-discogs-data').siblings('.spinner');
            const postId = $('#post_ID').val(); // <-- AÑADIR ESTA LÍNEA
            const resultsDiv = $('#dwoosync-results');

            spinner.addClass('is-active');
            importButton.prop('disabled', true).text('Importando...');
            
            // Mostrar indicador de carga centrado
            showLoadingIndicator(resultsDiv, 'Importando datos...');
            
            // --- INICIO: Obtener categorías seleccionadas de checkboxes nativos ---
            const selectedCategories = [];
            $('input[type="checkbox"][value]:checked').each(function() {
                const value = $(this).val();
                // Solo incluir si es un ID de categoría válido (números)
                if (/^\d+$/.test(value)) {
                    selectedCategories.push(value);
                }
            });
            console.log('Categorías seleccionadas para importación:', selectedCategories);
            // --- FIN: Obtener categorías seleccionadas de checkboxes nativos ---
            
            const importData = {
                action: 'wdi_import_discogs_release',
                nonce: wdi_secure_ajax.nonce,
                release_id: releaseId,
                post_id: postId,
                selected_categories: selectedCategories // <-- AÑADIR CATEGORÍAS SELECCIONADAS
            };
            
            $.post(wdi_secure_ajax.ajax_url, importData)
                .done(function(response) {
                    if (response.success) {
                    const data = response.data;
                    
                    // Ocultar indicador de carga
                    hideLoadingIndicator(resultsDiv);
                    
                    resultsDiv.html('<p style="color: green;">¡Datos rellenados con éxito!</p>');

                    // 1. Rellenar el título del producto (YA VIENE PROCESADO DESDE PHP)
                    if (data.title) {
                        $('#title').val(data.title).trigger('change');
                        $('#title-prompt-text').addClass('screen-reader-text');
                    }
                    
                    // 2. Rellenar Descripción Corta (ya viene procesada desde PHP)
                    if (data.short_description) {
                        if ($('#excerpt').length) {
                            $('#excerpt').val(data.short_description);
                             // Forzar actualización si hay TinyMCE
                            if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                                tinymce.get('excerpt').setContent(data.short_description);
                            }
                        }
                    }

                    // 3. Rellenar Descripción Larga (ya viene procesada desde PHP)
                    if (data.long_description) {
                        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                            tinymce.get('content').setContent(data.long_description);
                        } else {
                            $('#content').val(data.long_description);
                        }
                    }
                    
                    // 4. Preparar imágenes para la galería (esto ya lo hace el backend, pero lo dejamos para UI)
                    if(data.images && data.images.length > 0){
                         const imageUrls = data.images.join(',');
                         $('#discogs_gallery_to_import').val(imageUrls);

                         // --- INICIO: Mostrar previsualización bajo el botón de búsqueda ---
                         $('#discogs-preview-image').attr('src', data.images[0]);
                         $('#discogs-preview-text').text('Las demás imágenes se agregarán a la galería después de guardar el producto.');
                         $('#discogs-preview-wrapper').show();
                         // --- FIN: Mostrar previsualización ---
                    }

                    // El bloque que actualizaba la imagen destacada principal ha sido eliminado por petición.
                    
                    // --- INICIO: Añadir etiquetas automáticamente ---
                    if (data.tags && Array.isArray(data.tags) && data.tags.length > 0) {
                        const tagInput = $('#new-tag-product_tag');
                        const addButton = $('.tagadd');
                        
                        if (tagInput.length && addButton.length) {
                            // Limpiar etiquetas existentes para evitar duplicados en re-importaciones
                            $('.tagchecklist .ntdelbutton').click();
                            
                            // Añadir cada etiqueta nueva
                            data.tags.forEach(function(tag) {
                                tagInput.val(tag);
                                addButton.click();
                            });
                        }
                    }
                    // --- FIN: Añadir etiquetas automáticamente ---

                    // --- INICIO: Mostrar el botón de Publicar ---
                    if ($('#publish-from-importer').length) {
                        $('#publish-from-importer').show();
                    }
                    // --- FIN: Mostrar el botón de Publicar ---

                    // --- INICIO: Mostrar y activar campos rápidos ---
                    const $quickFieldsWrapper = $('#wdi-quick-fields-wrapper');
                    if ($quickFieldsWrapper.length) {
                        $quickFieldsWrapper.show();

                        // Activar el selector de categorías si existe
                        const $categorySelector = $('#wdi-quick-categories');
                        if ($categorySelector.length) {
                            try {
                                $categorySelector.select2({
                                    ajax: {
                                        url: wdi_secure_ajax.ajax_url,
                                        dataType: 'json',
                                        delay: 250,
                                        type: 'POST', // <-- AÑADIR ESTA LÍNEA
                                        data: function (params) {
                                            return {
                                                action: 'wdi_search_product_categories', // <-- NUESTRO PROPIO BUSCADOR
                                                nonce: wdi_secure_ajax.search_categories_nonce, // <-- Cambiado a 'nonce'
                                                term: params.term
                                            };
                                        },
                                        processResults: function( data ) {
                                            // El formato ya viene como lo necesita Select2
                                            return {
                                                results: data.results 
                                            };
                                        },
                                        cache: true
                                    },
                                    minimumInputLength: 2,
                                    placeholder: 'Buscar y seleccionar categorías...'
                                });
                            } catch (e) {
                                console.error('Error al inicializar Select2:', e);
                            }
                        }
                    }
                    // --- FIN: Mostrar y activar campos rápidos ---

                    // Cerrar la modal después de 1 segundo
                    setTimeout(closeModal, 1000);

                 } else {
                    // Ocultar indicador de carga en caso de error
                    hideLoadingIndicator(resultsDiv);
                    
                    // Manejar diferentes tipos de errores
                    let errorMessage = 'Error al importar: ';
                    if (response.data && response.data.message) {
                        errorMessage += response.data.message;
                    } else if (response.data) {
                        errorMessage += JSON.stringify(response.data);
                    } else {
                        errorMessage += 'Error desconocido';
                    }
                    
                    // Mostrar error en la interfaz en lugar de alert
                    resultsDiv.html('<div style="color: red; padding: 10px; border: 1px solid red; background: #ffe6e6; border-radius: 4px;">' + errorMessage + '</div>');
                    importButton.prop('disabled', false).text('Importar');
                    
                    console.error('Error en importación:', response);
                 }
                })
                .fail(function(xhr, status, error) {
                    // Ocultar indicador de carga en caso de error HTTP
                    hideLoadingIndicator(resultsDiv);
                    
                    let errorMessage = 'Error al importar: ';
                    
                    // Intentar parsear la respuesta JSON
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMessage += response.data.message;
                        } else {
                            errorMessage += 'Error HTTP ' + xhr.status + ': ' + error;
                        }
                    } catch (e) {
                        errorMessage += 'Error HTTP ' + xhr.status + ': ' + error;
                    }
                    
                    // Mostrar error en la interfaz
                    resultsDiv.html('<div style="color: red; padding: 10px; border: 1px solid red; background: #ffe6e6; border-radius: 4px;">' + errorMessage + '</div>');
                    importButton.prop('disabled', false).text('Importar');
                    
                    console.error('Error HTTP en importación:', xhr, status, error);
                })
                .always(function() {
                    spinner.removeClass('is-active');
                });
        });
        
        // --- INICIO: Funcionalidad del botón Publicar ---
        $('#dwoosync-wrapper').on('click', '#publish-from-importer', function(e) {
            e.preventDefault();
            const publishButton = $(this);
            const mainPublishButton = $('#publish');

            if (mainPublishButton.length) {
                publishButton.text('Publicando...').prop('disabled', true);
                mainPublishButton.click(); // Simula el clic en el botón principal de WordPress
            } else {
                alert('No se pudo encontrar el botón de publicación principal.');
            }
        });
        // --- FIN: Funcionalidad del botón Publicar ---


        // --- INICIO: Sincronización de campos rápidos ---
        
        // Sincronizar selector de categorías
        $('#dwoosync-wrapper').on('change', '#wdi-quick-categories', function() {
            const selectedCategories = $(this).val(); // Obtiene un array de IDs
            
            // Primero, desmarcar todas las categorías en la caja de checklist de WooCommerce
            $('#product_catchecklist input[type="checkbox"]').prop('checked', false);
            
            // Luego, marcar solo las que están seleccionadas en el Select2
            if (selectedCategories && selectedCategories.length > 0) { // <-- CORREGIDO
                $.each(selectedCategories, function(index, catId) {
                    $('#in-product_cat-' + catId).prop('checked', true);
                });
            }
        });

        // --- FIN: Sincronización de campos rápidos ---
        
    });

    // Función para mostrar warning en el modal
    function showWarningInModal(warningMessage) {
        console.log('Mostrando warning en modal:', warningMessage);
        
        // Buscar el modal
        const modal = $('#wdi-modal-content');
        const modalHeader = $('#wdi-modal-header');
        
        if (modal.length > 0 && modalHeader.length > 0) {
            // Remover warning anterior si existe
            modal.find('.discogs-warning').remove();
            
            // Los mensajes de warning ya vienen en inglés desde la API
            let translatedMessage = warningMessage;
            
            // Crear el warning
            const warningHtml = `
                <div class="discogs-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 13px;">
                    <strong>⚠️ ${wdi_admin_ajax.warning_label || 'Warning:'}</strong> ${translatedMessage.replace(/http:\/\/www\.dwoosync\.com/g, '<a href="http://www.dwoosync.com" target="_blank" style="color: #856404; text-decoration: underline;">http://www.dwoosync.com</a>')}
                </div>
            `;
            
            // Insertar después del header
            modalHeader.after(warningHtml);
            console.log('Warning agregado al modal');
        } else {
            console.log('No se encontró el modal o header');
        }
    }

    // --- FUNCIÓN PARA MARCAR CHECKBOXES NATIVOS DE WOOCOMMERCE ---
    function markNativeCategoryCheckboxes(categories, format) {
        console.log('Marcando categorías nativas:', categories, 'para formato:', format);
        
        // Limpiar categorías sugeridas anteriores
        $('#suggested-categories-wrapper').remove();
        
        // Mostrar notificación de categorías marcadas
        const resultsDiv = $('#dwoosync-results');
        let notificationHtml = `
            <div id="suggested-categories-wrapper" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin: 15px 0;">
                <h4 style="margin-top: 0; color: #155724; font-size: 14px;">
                    ✅ Categorías marcadas automáticamente para ${format}:
                </h4>
                <div id="suggested-categories-list" style="margin: 10px 0;">
        `;
        
        categories.forEach(function(category) {
            notificationHtml += `<span style="display: inline-block; background: #28a745; color: white; padding: 2px 8px; margin: 2px; border-radius: 3px; font-size: 12px;">${category.name}</span>`;
        });
        
        notificationHtml += `
                </div>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #155724;">
                    ✓ Revisa la pestaña "Producto" para ver las categorías marcadas.
                </p>
            </div>
        `;
        
        resultsDiv.append(notificationHtml);
        
        // Marcar los checkboxes nativos de WooCommerce
        setTimeout(function() {
            // --- INICIO: Limpiar categorías de formatos anteriores ---
            clearFormatCategories();
            // --- FIN: Limpiar categorías de formatos anteriores ---
            
            let markedCount = 0;
            categories.forEach(function(category) {
                // Buscar en todas las pestañas de categorías
                const checkboxes = $(`input[value="${category.id}"][type="checkbox"]`);
                checkboxes.each(function() {
                    if (!$(this).is(':checked')) {
                        $(this).prop('checked', true).trigger('change');
                        markedCount++;
                        console.log('Categoría nativa marcada:', category.name, 'ID:', category.id);
                    }
                });
            });
            
            console.log(`Categorías nativas marcadas: ${markedCount} de ${categories.length}`);
            
            // Mostrar mensaje de confirmación
            if (markedCount > 0) {
                const confirmationHtml = `
                    <div style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px;">
                        ✅ ${markedCount} categoría(s) marcada(s) automáticamente en la pestaña "Producto"
                    </div>
                `;
                resultsDiv.append(confirmationHtml);
            }
        }, 500);
        
        // Guardar las categorías sugeridas para usar en la importación
        window.suggestedCategories = categories;
        
        // Guardar todas las categorías de formatos para limpieza futura
        if (!window.formatCategories) {
            window.formatCategories = [];
        }
        categories.forEach(function(category) {
            if (window.formatCategories.indexOf(category.id) === -1) {
                window.formatCategories.push(category.id);
            }
        });
        
        console.log('Categorías nativas marcadas correctamente');
    }

    // --- FUNCIÓN PARA LIMPIAR CATEGORÍAS DE FORMATOS ANTERIORES ---
    function clearFormatCategories() {
        console.log('Limpiando categorías de formatos anteriores...');
        
        // Obtener todas las categorías configuradas para formatos
        const formatCategories = window.formatCategories || [];
        
        if (formatCategories.length === 0) {
            // Si no tenemos las categorías guardadas, intentar obtenerlas de la configuración
            // Esto se ejecutará la primera vez
            console.log('No hay categorías de formatos guardadas, saltando limpieza');
            return;
        }
        
        let clearedCount = 0;
        formatCategories.forEach(function(categoryId) {
            const checkboxes = $(`input[value="${categoryId}"][type="checkbox"]`);
            checkboxes.each(function() {
                if ($(this).is(':checked')) {
                    $(this).prop('checked', false).trigger('change');
                    clearedCount++;
                    console.log('Categoría desmarcada:', categoryId);
                }
            });
        });
        
        console.log(`Categorías de formatos desmarcadas: ${clearedCount}`);
    }

})( jQuery );

