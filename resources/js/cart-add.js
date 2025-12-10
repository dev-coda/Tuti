/**
 * Handle "Lo quiero" button clicks to add products to cart via AJAX
 * Uses event delegation to handle dynamically loaded buttons
 */
(function() {
    'use strict';
    
    function showToast(message, type, duration) {
        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast(message, type, duration);
        } else {
            // Fallback: use alert if toast not available
            console.warn('Toast not available, using alert');
            alert(message);
        }
    }
    
    function openCartModal() {
        if (window.openCart) {
            window.openCart();
        }
    }
    
    // Handle form submissions for forms containing data-add-to-cart buttons
    document.addEventListener('submit', async function(e) {
        const form = e.target;
        const button = form.querySelector('[data-add-to-cart]');
        if (!button) return;
        
        // Prevent default form submission
        e.preventDefault();
        e.stopPropagation();
        
        // Process the add to cart
        await processAddToCart(button, form);
    });
    
    // Also handle direct button clicks (backup)
    document.addEventListener('click', async function(e) {
        // Check if clicked element or its parent has data-add-to-cart
        let button = e.target.closest('[data-add-to-cart]');
        if (!button && e.target.hasAttribute && e.target.hasAttribute('data-add-to-cart')) {
            button = e.target;
        }
        if (!button) return;
        
        // Only handle if it's a submit button (to avoid double handling)
        if (button.type === 'submit') {
            const form = button.closest('form');
            if (form) {
                // Let the submit handler take care of it
                return;
            }
        }
        
        // Prevent default
        e.preventDefault();
        e.stopPropagation();
        
        const form = button.closest('form');
        if (form) {
            await processAddToCart(button, form);
        }
    });
    
    async function processAddToCart(button, form) {
        if (!form) {
            console.error('No form found for add to cart button');
            showToast('Error: No se encontró el formulario', 'error', 5000);
            return;
        }
        
        const formData = new FormData(form);
        const productId = button.dataset.productId || form.action.match(/\/(\d+)$/)?.[1];
        
        if (!productId) {
            console.error('Product ID not found', { button, form: form.action });
            showToast('Error: No se encontró el ID del producto', 'error', 5000);
            return;
        }
        
        // Disable button during request
        const originalText = button.innerHTML;
        const originalDisabled = button.disabled;
        button.disabled = true;
        button.innerHTML = '<span class="animate-spin inline-block">⏳</span>';
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                throw new Error('CSRF token not found');
            }
            
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken.content,
                    'Accept': 'application/json',
                },
                body: formData
            });
            
            // Check if response is JSON
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                // If not JSON, might be a redirect or HTML error page
                const text = await response.text();
                console.error('Non-JSON response:', text.substring(0, 200));
                throw new Error('El servidor respondió con un formato no esperado. ¿Estás autenticado?');
            }
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                // Show success toast
                showToast('Producto agregado', 'success', 3000);
                
                // Open cart modal if it exists
                setTimeout(() => {
                    openCartModal();
                }, 100);
                
                // Dispatch cart update event
                window.dispatchEvent(new CustomEvent('cart:updated'));
            } else {
                // Show error toast - handle both 400 and other error responses
                const errorMessage = data.message || data.error || 'Error al agregar el producto';
                showToast(errorMessage, 'error', 5000);
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showToast('Error al agregar el producto: ' + error.message, 'error', 5000);
        } finally {
            // Re-enable button
            button.disabled = originalDisabled;
            button.innerHTML = originalText;
        }
    }
    
    // Also attach to existing buttons on page load (for immediate binding)
    function attachToExistingButtons() {
        document.querySelectorAll('[data-add-to-cart]').forEach(function(button) {
            // Remove any existing listeners by cloning
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
        });
    }
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(attachToExistingButtons, 500);
        });
    } else {
        setTimeout(attachToExistingButtons, 500);
    }
})();
