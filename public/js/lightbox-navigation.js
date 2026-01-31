// Alpine.js Lightbox Navigator Component
window.lightboxNavigator = function(initialId, prevId, nextId, index, total, initialImageUrl, photosData = []) {
    return {
        // Photo data cache
        photos: {},
        
        // Current state
        currentId: initialId,
        currentImage: initialImageUrl || '',
        currentIndex: index,
        totalCount: total,
        hasPrev: prevId !== null,
        hasNext: nextId !== null,
        prevPhotoId: prevId,
        nextPhotoId: nextId,
        isLoading: false,
        
        // Initialize
        init() {
            console.log('🎬 Lightbox navigator initialized:', { 
                currentId: this.currentId, 
                prevId, 
                nextId, 
                initialImageUrl,
                photosCount: photosData.length 
            });
            this.loadPhotoData(photosData);
            this.setupKeyboardNav();
        },
        
        // Load photo data from backend (all filtered photos)
        async loadPhotoData(photosData = []) {
            // Clear existing photos
            this.photos = {};
            
            // Load all photos from backend data
            if (photosData && photosData.length > 0) {
                photosData.forEach(photo => {
                    this.photos[photo.id] = {
                        id: photo.id,
                        url: photo.url
                    };
                });
                
                console.log('📋 Loaded photos from backend:', photosData.length);
            } else {
                console.warn('⚠️ No photos data provided from backend, falling back to DOM parsing');
                
                // Fallback: Load from visible table rows (backwards compatibility)
                const allRows = document.querySelectorAll('tbody tr');
                const visibleRows = Array.from(allRows).filter(row => {
                    const style = window.getComputedStyle(row);
                    const isHidden = style.display === 'none' || 
                                   style.visibility === 'hidden' || 
                                   row.style.display === 'none' ||
                                   row.classList.contains('hidden') ||
                                   row.hasAttribute('hidden');
                    return !isHidden;
                });
                
                console.log('🔍 Found visible rows:', visibleRows.length, 'out of', allRows.length);
                
                visibleRows.forEach(row => {
                    if (row.cells && row.cells.length > 2) {
                        let id = null;
                        
                        for (let i = 0; i < row.cells.length; i++) {
                            const cellText = row.cells[i].textContent.trim();
                            const possibleId = parseInt(cellText);
                            
                            if (!isNaN(possibleId) && possibleId > 0 && cellText === possibleId.toString()) {
                                id = possibleId;
                                break;
                            }
                        }
                        
                        if (id) {
                            const previewUrl = `/photos/${id}/preview?w=1200`;
                            this.photos[id] = {
                                id: id,
                                url: previewUrl
                            };
                        }
                    }
                });
            }
            
            // Update total count
            this.totalCount = Object.keys(this.photos).length;
            
            console.log('📸 Total photos available:', this.totalCount);
            
            // Set initial image
            if (!this.currentImage && this.photos[this.currentId]) {
                this.currentImage = this.photos[this.currentId].url;
            }
            
            // Update navigation state
            this.updateNavigationState();
        },
        
        // Navigate to previous photo
        navigatePrev() {
            if (!this.hasPrev) return;
            
            console.log('⬅️ Navigate to prev:', this.prevPhotoId);
            this.navigateToPhoto(this.prevPhotoId);
        },
        
        // Navigate to next photo
        navigateNext() {
            if (!this.hasNext) return;
            
            console.log('➡️ Navigate to next:', this.nextPhotoId);
            this.navigateToPhoto(this.nextPhotoId);
        },
        
        // Update navigation state based on current photo and visible photos
        updateNavigationState() {
            // Get all photo IDs in descending order (to match table display)
            const photoIds = Object.keys(this.photos).map(Number).sort((a, b) => b - a);
            const currentIdx = photoIds.indexOf(this.currentId);
            
            // Update navigation state
            this.currentIndex = currentIdx + 1;
            this.prevPhotoId = currentIdx > 0 ? photoIds[currentIdx - 1] : null;
            this.nextPhotoId = currentIdx < photoIds.length - 1 ? photoIds[currentIdx + 1] : null;
            this.hasPrev = this.prevPhotoId !== null;
            this.hasNext = this.nextPhotoId !== null;
            
            console.log('🧭 Navigation updated:', {
                currentIndex: this.currentIndex,
                totalCount: this.totalCount,
                hasPrev: this.hasPrev,
                hasNext: this.hasNext,
                prevId: this.prevPhotoId,
                nextId: this.nextPhotoId
            });
        },
        
        // Navigate to specific photo
        navigateToPhoto(photoId) {
            if (!photoId || !this.photos[photoId]) {
                console.error('❌ Photo not found in available photos:', photoId);
                return;
            }
            
            // Show loading spinner
            this.isLoading = true;
            
            // Update current photo
            this.currentId = photoId;
            this.currentImage = this.photos[photoId].url;
            
            // Update navigation state
            this.updateNavigationState();
            
            // Update modal heading - try multiple selectors for Filament modals
            const heading = document.querySelector('[data-state="open"] h2') || 
                           document.querySelector('[role="dialog"] h2') ||
                           document.querySelector('.fi-modal-heading h2') ||
                           document.querySelector('[x-data*="lightboxNavigator"]')?.closest('[role="dialog"]')?.querySelector('h2');
            
            if (heading) {
                heading.textContent = `Kép előnézet - ID: ${photoId}`;
                console.log('✅ Modal heading updated:', heading.textContent);
            } else {
                console.warn('⚠️ Could not find modal heading to update');
            }
            
            console.log('✅ Navigated to photo:', photoId);
        },
        
        // Navigate to photo in main list (close modal and filter to this photo)
        navigateToPhotoInList() {
            console.log('🎯 Navigate to photo in list:', this.currentId);
            
            // Close the modal first
            this.closeModal();
            
            // Wait a bit for modal to close, then apply filter
            setTimeout(() => {
                this.applyPhotoFilter(this.currentId);
            }, 300);
        },
        
        // Close the current modal
        closeModal() {
            // Try different methods to close Filament modal
            let closeButton = document.querySelector('[data-state="open"] button[aria-label="Close"]') ||
                             document.querySelector('[data-state="open"] button[type="button"]:last-child') ||
                             document.querySelector('[role="dialog"] .fi-modal-close-btn');
            
            // If not found, search for button with "Bezárás" text
            if (!closeButton) {
                const buttons = document.querySelectorAll('[role="dialog"] button');
                for (const button of buttons) {
                    if (button.textContent && button.textContent.trim() === 'Bezárás') {
                        closeButton = button;
                        break;
                    }
                }
            }
            
            // Also try to find cancel button in modal
            if (!closeButton) {
                closeButton = document.querySelector('[data-state="open"] [data-close-modal]') ||
                             document.querySelector('[role="dialog"] [data-close-modal]') ||
                             document.querySelector('.fi-modal-footer button:last-child');
            }
            
            if (closeButton) {
                closeButton.click();
                console.log('✅ Modal closed via close button');
            } else {
                // Try pressing Escape key
                document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
                console.log('✅ Modal closed via Escape key');
            }
        },
        
        // Apply filter to show only the specific photo
        applyPhotoFilter(photoId) {
            try {
                // Method 1: Try to find Filament's search input
                const searchInput = document.querySelector('input[type="search"]') ||
                                  document.querySelector('input[placeholder*="Keresés"]') ||
                                  document.querySelector('.fi-ta-search-field input') ||
                                  document.querySelector('[data-testid="table.search.field"] input');
                
                if (searchInput) {
                    console.log('🔍 Found search input, applying filter...');
                    
                    // Focus the input first
                    searchInput.focus();
                    
                    // Clear existing search
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    searchInput.dispatchEvent(new Event('change', { bubbles: true }));
                    
                    // Set search to photo ID after a short delay
                    setTimeout(() => {
                        searchInput.value = photoId.toString();
                        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                        searchInput.dispatchEvent(new Event('change', { bubbles: true }));
                        searchInput.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                        
                        // Also trigger Alpine.js events if present
                        if (window.Alpine) {
                            searchInput.dispatchEvent(new CustomEvent('input', { bubbles: true }));
                        }
                        
                        console.log('✅ Applied search filter:', photoId);
                    }, 200);
                } else {
                    console.log('🔍 Search input not found, trying URL method...');
                    
                    // Method 2: Try to manipulate URL parameters and reload
                    const url = new URL(window.location);
                    url.searchParams.set('tableSearch', photoId.toString());
                    
                    // Use replace instead of pushState to avoid history pollution
                    window.location.replace(url.toString());
                    
                    console.log('✅ Applied URL filter and reloading:', photoId);
                }
            } catch (error) {
                console.error('❌ Failed to apply photo filter:', error);
                
                // Fallback: just reload the page with search parameter
                const url = new URL(window.location);
                url.searchParams.set('tableSearch', photoId.toString());
                window.location.href = url.toString();
            }
        },
        
        // Setup keyboard navigation
        setupKeyboardNav() {
            // Remove any existing listeners first
            if (window.lightboxKeyHandler) {
                document.removeEventListener('keydown', window.lightboxKeyHandler);
            }
            
            // Create new handler and store reference
            window.lightboxKeyHandler = (e) => {
                const modalOpen = document.querySelector('[data-state="open"]');
                if (!modalOpen) return;
                
                // Check if we're in the right modal (has our Alpine component)
                const alpineComponent = modalOpen.querySelector('[x-data*="lightboxNavigator"]');
                if (!alpineComponent) return;
                
                if (e.key === 'ArrowLeft' && this.hasPrev) {
                    e.preventDefault();
                    console.log('⌨️ Left arrow pressed - navigating to prev');
                    this.navigatePrev();
                } else if (e.key === 'ArrowRight' && this.hasNext) {
                    e.preventDefault();
                    console.log('⌨️ Right arrow pressed - navigating to next');
                    this.navigateNext();
                }
            };
            
            document.addEventListener('keydown', window.lightboxKeyHandler);
            console.log('⌨️ Keyboard navigation enabled');
        }
    };
};

// Global event listener for notification actions
// This needs to be outside the Alpine component because notifications appear when no modal is open
document.addEventListener('DOMContentLoaded', function() {
    // Listen for apply-photo-filter custom event
    window.addEventListener('apply-photo-filter', function(event) {
        console.log('🎯 Received apply-photo-filter event:', event);
        const photoId = event?.detail?.photoId;
        
        if (photoId) {
            // Close any open modal first
            const closeButton = document.querySelector('[data-state="open"] button[aria-label="Close"]') ||
                              document.querySelector('[data-state="open"] button[type="button"]:last-child') ||
                              document.querySelector('[role="dialog"] .fi-modal-close-btn');
            
            if (closeButton) {
                closeButton.click();
                console.log('✅ Modal closed');
            }
            
            // Apply the filter after a short delay
            setTimeout(() => {
                applyPhotoFilterGlobal(photoId);
            }, 300);
        }
    });
    
    console.log('📡 Event listener registered for apply-photo-filter');
});

// Global function to apply photo filter (used by notification actions)
function applyPhotoFilterGlobal(photoId) {
    try {
        // Find Filament's search input
        const searchInput = document.querySelector('input[type="search"]') ||
                          document.querySelector('input[placeholder*="Keresés"]') ||
                          document.querySelector('.fi-ta-search-field input') ||
                          document.querySelector('[data-testid="table.search.field"] input');
        
        if (searchInput) {
            console.log('🔍 Found search input, applying filter...');
            
            // Focus the input first
            searchInput.focus();
            
            // Clear existing search
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Set search to photo ID after a short delay
            setTimeout(() => {
                searchInput.value = photoId.toString();
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                searchInput.dispatchEvent(new Event('change', { bubbles: true }));
                searchInput.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                
                // Also trigger Alpine.js events if present
                if (window.Alpine) {
                    searchInput.dispatchEvent(new CustomEvent('input', { bubbles: true }));
                }
                
                console.log('✅ Applied search filter:', photoId);
            }, 200);
        } else {
            console.log('🔍 Search input not found, trying URL method...');
            
            // Fallback: Try to manipulate URL parameters and reload
            const url = new URL(window.location);
            url.searchParams.set('tableSearch', photoId.toString());
            
            // Use replace instead of pushState to avoid history pollution
            window.location.replace(url.toString());
            
            console.log('✅ Applied URL filter and reloading:', photoId);
        }
    } catch (error) {
        console.error('❌ Failed to apply photo filter:', error);
        
        // Fallback: just reload the page with search parameter
        const url = new URL(window.location);
        url.searchParams.set('tableSearch', photoId.toString());
        window.location.href = url.toString();
    }
}

console.log('✅ Lightbox navigation loaded with Alpine.js');

