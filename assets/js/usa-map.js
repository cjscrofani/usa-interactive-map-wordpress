/**
 * USA Interactive Map Frontend JavaScript
 * Version 2.0.0
 */

(function($) {
    'use strict';
    
    class USAInteractiveMap {
        constructor(element) {
            this.$wrapper = $(element);
            this.$container = this.$wrapper.parent();
            this.mapId = this.$wrapper.attr('id');
            this.settings = window.usa_map_vars || {};
            this.displayMode = this.$wrapper.data('display-mode') || 'tooltip';
            this.postTypes = this.$wrapper.data('post-types') || '';
            
            this.$tooltip = null;
            this.$sidebar = null;
            this.$resultsBelow = null;
            this.activeState = null;
            this.isInitialized = false;
            
            this.init();
        }
        
        init() {
            if (this.settings.debug) {
                console.log('USA Map: Initializing map instance', this.mapId);
            }
            
            // Wait for SVG to be ready
            this.waitForSVG().then(() => {
                this.setupDisplay();
                this.bindEvents();
                this.applySettings();
                this.isInitialized = true;
                
                // Trigger custom event
                this.$wrapper.trigger('usa-map:initialized', [this]);
            });
        }
        
        waitForSVG() {
            return new Promise((resolve) => {
                const checkSVG = () => {
                    const $svg = this.$wrapper.find('svg.usa-map-svg');
                    
                    if ($svg.length && $svg.attr('data-map-ready') === 'true') {
                        const $paths = $svg.find('path.state');
                        if ($paths.length > 0) {
                            if (this.settings.debug) {
                                console.log('USA Map: SVG ready with', $paths.length, 'states');
                            }
                            resolve();
                            return;
                        }
                    }
                    
                    setTimeout(checkSVG, 100);
                };
                
                checkSVG();
            });
        }
        
        setupDisplay() {
            switch (this.displayMode) {
                case 'tooltip':
                    this.setupTooltip();
                    break;
                case 'sidebar':
                    this.setupSidebar();
                    break;
                case 'modal':
                    this.setupModal();
                    break;
                case 'below':
                    this.setupBelowDisplay();
                    break;
            }
        }
        
        setupTooltip() {
            this.$tooltip = this.$wrapper.find('.state-tooltip');
            
            if (this.$tooltip.length === 0) {
                // Create tooltip if not exists
                const tooltipHTML = `
                    <div class="state-tooltip" style="display: none;">
                        <div class="tooltip-header">
                            <h3 class="state-name"></h3>
                            <button class="close-tooltip" aria-label="Close">&times;</button>
                        </div>
                        <div class="tooltip-content">
                            <div class="loading-spinner">Loading...</div>
                            <div class="posts-container"></div>
                        </div>
                    </div>
                `;
                this.$wrapper.append(tooltipHTML);
                this.$tooltip = this.$wrapper.find('.state-tooltip');
            }
        }
        
        setupSidebar() {
            this.$sidebar = this.$wrapper.find('.map-sidebar');
        }
        
        setupModal() {
            // Create modal container if needed
            if ($('#usa-map-modal').length === 0) {
                const modalHTML = `
                    <div id="usa-map-modal" class="usa-map-modal" style="display: none;">
                        <div class="modal-overlay"></div>
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="state-name"></h3>
                                <button class="close-modal" aria-label="Close">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="loading-spinner">Loading...</div>
                                <div class="posts-container"></div>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(modalHTML);
            }
        }
        
        setupBelowDisplay() {
            this.$resultsBelow = this.$wrapper.find('.map-results-below');
        }
        
        bindEvents() {
            const self = this;
            
            // State click/keyboard events
            this.$wrapper.on('click.usaMap', 'path.state', function(e) {
                e.preventDefault();
                self.handleStateInteraction($(this));
            });
            
            this.$wrapper.on('keypress.usaMap', 'path.state', function(e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    self.handleStateInteraction($(this));
                }
            });
            
            // State hover effects
            this.$wrapper.on('mouseenter.usaMap', 'path.state', function() {
                $(this).addClass('hover');
            });
            
            this.$wrapper.on('mouseleave.usaMap', 'path.state', function() {
                $(this).removeClass('hover');
            });
            
            // Close buttons
            $(document).on('click.usaMap' + this.mapId, '.close-tooltip, .close-modal', () => {
                this.closeDisplay();
            });
            
            // Click outside to close
            if (this.displayMode === 'tooltip') {
                $(document).on('click.usaMapOutside' + this.mapId, (e) => {
                    if (!$(e.target).closest('.state-tooltip, path.state').length) {
                        this.closeDisplay();
                    }
                });
            }
            
            // Modal overlay click
            if (this.displayMode === 'modal') {
                $(document).on('click.usaMapModal' + this.mapId, '.modal-overlay', () => {
                    this.closeDisplay();
                });
            }
            
            // ESC key
            $(document).on('keydown.usaMapEsc' + this.mapId, (e) => {
                if (e.which === 27 && this.activeState) {
                    this.closeDisplay();
                }
            });
            
            // Window resize
            let resizeTimeout;
            $(window).on('resize.usaMap' + this.mapId, () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    if (this.activeState && this.displayMode === 'tooltip') {
                        this.positionTooltip();
                    }
                }, 250);
            });
            
            // Search within results
            if (this.settings.settings && this.settings.settings.enable_search) {
                $(document).on('input.usaMapSearch' + this.mapId, '.search-results', function() {
                    self.filterResults($(this).val());
                });
            }
        }
        
        handleStateInteraction($state) {
            const stateAbbr = $state.attr('id');
            const stateName = $state.attr('data-state');
            
            if (this.settings.debug) {
                console.log('USA Map: State clicked -', stateName, '(' + stateAbbr + ')');
            }
            
            // If clicking same state, close
            if (this.activeState === stateAbbr && this.displayMode === 'tooltip') {
                this.closeDisplay();
                return;
            }
            
            // Update active state
            this.$wrapper.find('path.state').removeClass('active');
            $state.addClass('active');
            this.activeState = stateAbbr;
            
            // Load and display content
            this.loadStateContent(stateName, $state);
        }
        
        loadStateContent(stateName, $state) {
            // Show loading state
            this.showLoading(stateName);
            
            // Position display (for tooltip mode)
            if (this.displayMode === 'tooltip') {
                this.positionTooltip($state);
            }
            
            // AJAX request
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'usa_map_get_state_posts',
                    state: stateName,
                    post_types: this.postTypes,
                    nonce: this.settings.nonce
                },
                success: (response) => {
                    this.displayContent(response, stateName);
                },
                error: (xhr, status, error) => {
                    console.error('USA Map: AJAX error', error);
                    this.displayError();
                }
            });
        }
        
        showLoading(stateName) {
            switch (this.displayMode) {
                case 'tooltip':
                    this.$tooltip.find('.state-name').text(stateName);
                    this.$tooltip.find('.loading-spinner').show();
                    this.$tooltip.find('.posts-container').empty();
                    this.$tooltip.fadeIn(this.settings.settings.animation_speed || 300);
                    break;
                    
                case 'sidebar':
                    this.$sidebar.find('.sidebar-title').text(stateName);
                    this.$sidebar.find('.sidebar-content').html('<div class="loading-spinner">Loading...</div>');
                    break;
                    
                case 'modal':
                    const $modal = $('#usa-map-modal');
                    $modal.find('.state-name').text(stateName);
                    $modal.find('.loading-spinner').show();
                    $modal.find('.posts-container').empty();
                    $modal.fadeIn(this.settings.settings.animation_speed || 300);
                    break;
                    
                case 'below':
                    this.$resultsBelow.find('.results-title').text(stateName);
                    this.$resultsBelow.find('.results-header').show();
                    this.$resultsBelow.find('.results-content').html('<div class="loading-spinner">Loading...</div>');
                    
                    // Smooth scroll to results
                    $('html, body').animate({
                        scrollTop: this.$resultsBelow.offset().top - 100
                    }, 500);
                    break;
            }
        }
        
        displayContent(content, stateName) {
            let $container;
            
            switch (this.displayMode) {
                case 'tooltip':
                    this.$tooltip.find('.loading-spinner').hide();
                    $container = this.$tooltip.find('.posts-container');
                    break;
                    
                case 'sidebar':
                    $container = this.$sidebar.find('.sidebar-content');
                    break;
                    
                case 'modal':
                    const $modal = $('#usa-map-modal');
                    $modal.find('.loading-spinner').hide();
                    $container = $modal.find('.posts-container');
                    break;
                    
                case 'below':
                    $container = this.$resultsBelow.find('.results-content');
                    break;
            }
            
            $container.html(content);
            
            // Animate items
            $container.find('.state-post-item').each(function(index) {
                $(this).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                }).delay(index * 50).animate({
                    opacity: 1
                }, 300).css({
                    transform: 'translateY(0)'
                });
            });
            
            // Trigger custom event
            this.$wrapper.trigger('usa-map:content-loaded', [stateName, content]);
        }
        
        displayError() {
            const errorMessage = '<div class="error-message">Error loading content. Please try again.</div>';
            
            switch (this.displayMode) {
                case 'tooltip':
                    this.$tooltip.find('.loading-spinner').hide();
                    this.$tooltip.find('.posts-container').html(errorMessage);
                    break;
                    
                case 'sidebar':
                    this.$sidebar.find('.sidebar-content').html(errorMessage);
                    break;
                    
                case 'modal':
                    const $modal = $('#usa-map-modal');
                    $modal.find('.loading-spinner').hide();
                    $modal.find('.posts-container').html(errorMessage);
                    break;
                    
                case 'below':
                    this.$resultsBelow.find('.results-content').html(errorMessage);
                    break;
            }
        }
        
        positionTooltip($state) {
            if (!$state || !this.$tooltip) return;
            
            const stateRect = $state[0].getBoundingClientRect();
            const containerRect = this.$wrapper[0].getBoundingClientRect();
            
            // Calculate center of state
            let left = stateRect.left - containerRect.left + (stateRect.width / 2);
            let top = stateRect.top - containerRect.top + (stateRect.height / 2);
            
            // Get tooltip dimensions
            const tooltipWidth = this.$tooltip.outerWidth();
            const tooltipHeight = this.$tooltip.outerHeight();
            
            // Center tooltip on state
            left -= tooltipWidth / 2;
            top -= tooltipHeight / 2;
            
            // Keep within bounds
            const padding = 20;
            const maxLeft = containerRect.width - tooltipWidth - padding;
            const maxTop = containerRect.height - tooltipHeight - padding;
            
            left = Math.max(padding, Math.min(left, maxLeft));
            top = Math.max(padding, Math.min(top, maxTop));
            
            this.$tooltip.css({
                left: left + 'px',
                top: top + 'px'
            });
        }
        
        closeDisplay() {
            this.activeState = null;
            this.$wrapper.find('path.state').removeClass('active');
            
            switch (this.displayMode) {
                case 'tooltip':
                    this.$tooltip.fadeOut(this.settings.settings.animation_speed || 300);
                    break;
                    
                case 'modal':
                    $('#usa-map-modal').fadeOut(this.settings.settings.animation_speed || 300);
                    break;
                    
                case 'sidebar':
                    this.$sidebar.find('.sidebar-title').text('Select a State');
                    this.$sidebar.find('.sidebar-content').html(
                        '<p class="sidebar-instruction">Click on any state to view content.</p>'
                    );
                    break;
                    
                case 'below':
                    // Don't clear below display, just remove active state
                    break;
            }
            
            // Trigger custom event
            this.$wrapper.trigger('usa-map:closed');
        }
        
        filterResults(searchTerm) {
            const $items = $('.state-post-item');
            
            if (!searchTerm) {
                $items.show();
                return;
            }
            
            searchTerm = searchTerm.toLowerCase();
            
            $items.each(function() {
                const $item = $(this);
                const text = $item.text().toLowerCase();
                
                if (text.indexOf(searchTerm) > -1) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        }
        
        applySettings() {
            // Apply count badges if enabled
            if (window.usaMapCounts) {
                this.addCountBadges(window.usaMapCounts);
            }
            
            // Apply custom colors if set
            if (this.settings.settings && this.settings.settings.colors) {
                // Colors are applied via CSS variables set in PHP
            }
        }
        
        addCountBadges(counts) {
            const $svg = this.$wrapper.find('svg.usa-map-svg');
            
            // Create a group for badges if not exists
            let $badgeGroup = $svg.find('.count-badges');
            if ($badgeGroup.length === 0) {
                $badgeGroup = $('<g class="count-badges"></g>');
                $svg.append($badgeGroup);
            }
            
            // Add badges for each state with counts
            Object.keys(counts).forEach(stateAbbr => {
                const count = counts[stateAbbr];
                if (count > 0) {
                    const $state = $svg.find('#' + stateAbbr);
                    if ($state.length) {
                        // Get state center
                        const bbox = $state[0].getBBox();
                        const cx = bbox.x + bbox.width / 2;
                        const cy = bbox.y + bbox.height / 2;
                        
                        // Create badge
                        const badge = `
                            <g class="count-badge" data-state="${stateAbbr}">
                                <circle cx="${cx}" cy="${cy}" r="12" class="badge-bg" />
                                <text x="${cx}" y="${cy}" class="badge-text">${count}</text>
                            </g>
                        `;
                        
                        $badgeGroup.append(badge);
                    }
                }
            });
        }
        
        destroy() {
            // Unbind events
            this.$wrapper.off('.usaMap');
            $(document).off('.usaMap' + this.mapId);
            $(document).off('.usaMapOutside' + this.mapId);
            $(document).off('.usaMapModal' + this.mapId);
            $(document).off('.usaMapEsc' + this.mapId);
            $(document).off('.usaMapSearch' + this.mapId);
            $(window).off('.usaMap' + this.mapId);
            
            // Remove tooltip if created
            if (this.$tooltip) {
                this.$tooltip.remove();
            }
            
            // Clear active state
            this.activeState = null;
            
            // Trigger custom event
            this.$wrapper.trigger('usa-map:destroyed');
        }
    }
    
    // jQuery plugin wrapper
    $.fn.usaInteractiveMap = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('usaMapInstance');
            
            if (!instance) {
                instance = new USAInteractiveMap(this);
                $this.data('usaMapInstance', instance);
            }
            
            if (typeof options === 'string' && typeof instance[options] === 'function') {
                instance[options]();
            }
        });
    };
    
    // Auto-initialize on ready
    $(document).ready(function() {
        $('.usa-map-wrapper').usaInteractiveMap();
    });
    
    // Also initialize on window load as fallback
    $(window).on('load', function() {
        $('.usa-map-wrapper:not([data-initialized])').each(function() {
            $(this).usaInteractiveMap().attr('data-initialized', 'true');
        });
    });
    
})(jQuery);
