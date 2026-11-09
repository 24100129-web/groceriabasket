document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const menuToggle = document.getElementById('menuToggle');
  const searchInput = document.getElementById('productSearch');
  const searchResults = document.getElementById('searchResults');
  const addBundleButton = document.getElementById('addBundleButton');
  const bundleForm = document.getElementById('bundleForm');
  const closeBundleForm = document.getElementById('closeBundleForm');
  const bundleFormTitle = document.getElementById('bundleFormTitle');
  const bundleId = document.getElementById('bundleId');
  const bundleSubmitButton = document.getElementById('bundleSubmitButton');
  const bundleName = document.querySelector('[name="bundle_name"]');
  const bundleDescription = document.querySelector('[name="bundle_description"]');
  const bundlePrice = document.querySelector('[name="bundle_price"]');
  const bundleStatus = document.querySelector('[name="bundle_status"]');
  const bundleImage = document.querySelector('[name="bundle_image"]');
  const addProductButton = document.getElementById('addProductButton');
  const productForm = document.getElementById('productForm');
  const closeProductForm = document.getElementById('closeProductForm');
  const cartJson = document.getElementById('cartJson');
  const cartList = document.getElementById('cartList');
  const totalAmount = document.getElementById('totalAmount');
  const subtotalAmount = document.getElementById('subtotalAmount');
  const subtotalLabel = document.getElementById('subtotalLabel');
  const cashReceived = document.getElementById('cashReceived');
  const changeDue = document.getElementById('changeDue');
  const paymentMethod = document.getElementById('paymentMethod');
  const gcashPaymentPanel = document.getElementById('gcashPaymentPanel');
  const cashPaymentDetails = document.getElementById('cashPaymentDetails');
  const paymentMethodButtons = document.querySelectorAll('[data-payment-method]');
  const posSearch = document.getElementById('posSearch');
  const posProducts = document.querySelectorAll('.pos-product');
  const saleForm = document.getElementById('saleForm');
  const clearCartButton = document.getElementById('clearCart');
  const receiptModal = document.getElementById('receiptModal');
  const newSaleButton = document.getElementById('newSale');

  const products = [
    ['Rice 5kg', '₱285.00'], ['Bear Brand Milk', '₱85.00'], ['Lucky Me Noodles', '₱15.00'], ['Bread', '₱55.00'],
    ['Fresh Eggs', '₱96.00'], ['Nescafe Coffee', '₱78.00'], ['Canned Sardines', '₱32.00'], ['Soft Drinks', '₱75.00']
  ];

  const cart = [];

  function updateCartJson() {
    cartJson.value = JSON.stringify(cart);
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value || 0);
  }

  function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + item.qty * item.price, 0);
    const received = Number(cashReceived?.value || 0);
    const change = paymentMethod?.value === 'gcash' ? 0 : Math.max(0, received - subtotal);

    totalAmount.textContent = formatMoney(subtotal);
    subtotalAmount.textContent = formatMoney(subtotal);
    subtotalLabel.textContent = `Subtotal (${cart.reduce((sum, item) => sum + item.qty, 0)} items)`;
    changeDue.textContent = formatMoney(change);
    updateCartJson();
  }

  function renderCart() {
    if (!cartList) return;

    if (!cart.length) {
      cartList.innerHTML = '<div class="pos-cart-empty">No items selected</div>';
      updateTotals();
      return;
    }

    cartList.innerHTML = cart.map((item) => `
      <div class="cart-row" data-name="${item.name}">
        <div class="product-info">
          <b>${item.name}</b>
          <div class="quantity">${item.qty} × ${formatMoney(item.price)}</div>
        </div>
        <div class="stepper">
          <button type="button" data-action="decrease" data-name="${item.name}">−</button>
          <span>${item.qty}</span>
          <button type="button" data-action="increase" data-name="${item.name}">+</button>
        </div>
        <strong>${formatMoney(item.qty * item.price)}</strong>
        <button type="button" class="remove-item" data-action="remove" data-name="${item.name}">×</button>
      </div>
    `).join('');

    updateTotals();
  }

  function addToCart(name, price, stock) {
    if (stock <= 0) {
      alert('This product is out of stock.');
      return;
    }

    const existing = cart.find((item) => item.name === name);
    if (existing) {
      if (existing.qty >= stock) {
        alert('You cannot add more than the available stock.');
        return;
      }
      existing.qty += 1;
    } else {
      cart.push({ name, price, qty: 1 });
    }

    renderCart();
  }

  function adjustQty(name, direction) {
    const item = cart.find((entry) => entry.name === name);
    if (!item) return;
    const stock = Number(document.querySelector(`.pos-product[data-product-name="${CSS.escape(name)}"]`)?.dataset.productStock || 0);

    if (direction === 'increase') {
      if (item.qty >= stock) {
        alert('Not enough stock available for this item.');
        return;
      }
      item.qty += 1;
    } else {
      item.qty -= 1;
      if (item.qty <= 0) {
        const index = cart.findIndex((entry) => entry.name === name);
        cart.splice(index, 1);
      }
    }

    renderCart();
  }

  posProducts.forEach((button) => {
    button.addEventListener('click', () => {
      const name = button.dataset.productName;
      const price = Number(button.dataset.productPrice || 0);
      const stock = Number(button.dataset.productStock || 0);
      addToCart(name, price, stock);
    });
  });

  if (cartList) {
    cartList.addEventListener('click', (event) => {
      const target = event.target.closest('[data-action]');
      if (!target) return;
      const name = target.dataset.name;
      const action = target.dataset.action;

      if (action === 'increase') { adjustQty(name, 'increase'); }
      if (action === 'decrease') { adjustQty(name, 'decrease'); }
      if (action === 'remove') {
        const index = cart.findIndex((entry) => entry.name === name);
        if (index >= 0) cart.splice(index, 1);
        renderCart();
      }
    });
  }

  cashReceived?.addEventListener('input', () => updateTotals());

  function setPaymentMethod(method) {
    if (!paymentMethod) return;
    paymentMethod.value = method;
    paymentMethodButtons.forEach((button) => button.classList.toggle('selected', button.dataset.paymentMethod === method));
    if (gcashPaymentPanel) gcashPaymentPanel.hidden = method !== 'gcash';
    if (cashPaymentDetails) cashPaymentDetails.hidden = method !== 'cash';
    updateTotals();
  }

  paymentMethodButtons.forEach((button) => {
    button.addEventListener('click', () => setPaymentMethod(button.dataset.paymentMethod));
  });

  document.querySelectorAll('.cash-button').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.cash-button').forEach((item) => item.classList.remove('selected'));
      button.classList.add('selected');

      const value = button.dataset.value;
      if (value === 'exact') {
        const subtotal = cart.reduce((sum, item) => sum + item.qty * item.price, 0);
        cashReceived.value = subtotal;
      } else {
        cashReceived.value = value;
      }
      updateTotals();
    });
  });

  saleForm?.addEventListener('submit', (event) => {
    if (!cart.length) {
      event.preventDefault();
      alert('Please add at least one item before completing the sale.');
      return;
    }

    const subtotal = cart.reduce((sum, item) => sum + item.qty * item.price, 0);
    const received = Number(cashReceived?.value || 0);
    if (paymentMethod?.value !== 'gcash' && received < subtotal) {
      event.preventDefault();
      alert('The cash received is less than the total. Please enter enough cash.');
      return;
    }

    cartJson.value = JSON.stringify(cart);
  });

  clearCartButton?.addEventListener('click', () => {
    cart.length = 0;
    renderCart();
    if (cashReceived) cashReceived.value = 0;
    setPaymentMethod('cash');
    updateTotals();
  });

  newSaleButton?.addEventListener('click', () => {
    receiptModal.hidden = true;
    cart.length = 0;
    if (cashReceived) cashReceived.value = 0;
    setPaymentMethod('cash');
    renderCart();
    updateTotals();
  });

  menuToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
  });

  addBundleButton?.addEventListener('click', () => {
    bundleFormTitle.textContent = 'Add Online Bundle';
    bundleSubmitButton.textContent = 'Save Bundle';
    bundleId.value = '0';
    bundleName.value = '';
    bundleDescription.value = '';
    bundlePrice.value = '0';
    bundleStatus.value = 'Available';
    bundleImage.value = '';
    document.querySelectorAll('.bundle-item-checkbox').forEach((checkbox) => {
      checkbox.checked = false;
      checkbox.closest('.check-row').querySelector('.quantity-input').value = '1';
    });
    if (bundleForm) bundleForm.hidden = false;
    bundleForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.querySelectorAll('.edit-bundle-button').forEach((button) => {
    button.addEventListener('click', () => {
      const items = JSON.parse(button.dataset.bundleItems || '[]');
      const includedItems = new Map(items.map((item) => [item.product_name.toLowerCase(), item.quantity]));
      bundleFormTitle.textContent = 'Edit Online Bundle';
      bundleSubmitButton.textContent = 'Update Bundle';
      bundleId.value = button.dataset.bundleId;
      bundleName.value = button.dataset.bundleName;
      bundleDescription.value = button.dataset.bundleDescription;
      bundlePrice.value = button.dataset.bundlePrice;
      bundleStatus.value = button.dataset.bundleStatus;
      bundleImage.value = '';
      document.querySelectorAll('.bundle-item-checkbox').forEach((checkbox) => {
        const quantityInput = checkbox.closest('.check-row').querySelector('.quantity-input');
        const quantity = includedItems.get(checkbox.value.toLowerCase());
        checkbox.checked = quantity !== undefined;
        quantityInput.value = quantity || 1;
      });
      bundleForm.hidden = false;
      bundleForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  closeBundleForm?.addEventListener('click', () => {
    if (bundleForm) bundleForm.hidden = true;
  });

  addProductButton?.addEventListener('click', () => {
    if (productForm) productForm.hidden = false;
    productForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  closeProductForm?.addEventListener('click', () => {
    if (productForm) productForm.hidden = true;
  });

  const deleteProductModal = document.getElementById('deleteProductModal');
  const deleteProductForm = document.getElementById('deleteProductForm');
  const deleteProductId = document.getElementById('deleteProductId');
  const deleteProductName = document.getElementById('deleteProductName');
  const deleteDetailsStep = document.getElementById('deleteDetailsStep');
  const deleteConfirmStep = document.getElementById('deleteConfirmStep');
  const deletionReason = document.getElementById('deletionReason');
  const deleteAdminPassword = document.getElementById('deleteAdminPassword');
  const confirmDeleteValue = document.getElementById('confirmDeleteValue');

  function closeDeleteModal() {
    if (!deleteProductModal) return;
    deleteProductModal.hidden = true;
    deleteProductForm?.reset();
    if (deleteDetailsStep) deleteDetailsStep.hidden = false;
    if (deleteConfirmStep) deleteConfirmStep.hidden = true;
    if (confirmDeleteValue) confirmDeleteValue.value = '0';
  }

  document.querySelectorAll('[data-delete-product]').forEach((button) => {
    button.addEventListener('click', () => {
      deleteProductId.value = button.dataset.deleteProduct;
      deleteProductName.textContent = button.dataset.productName;
      deletionReason.value = '';
      deleteAdminPassword.value = '';
      deleteDetailsStep.hidden = false;
      deleteConfirmStep.hidden = true;
      confirmDeleteValue.value = '0';
      deleteProductModal.hidden = false;
      deletionReason.focus();
    });
  });

  document.getElementById('reviewDeleteButton')?.addEventListener('click', () => {
    if (!deleteProductForm.reportValidity()) return;
    document.getElementById('confirmProductName').textContent = deleteProductName.textContent;
    document.getElementById('confirmDeleteReason').textContent = deletionReason.value.trim();
    deleteDetailsStep.hidden = true;
    deleteConfirmStep.hidden = false;
  });

  document.querySelectorAll('[data-cancel-delete]').forEach((button) => button.addEventListener('click', closeDeleteModal));
  deleteProductModal?.addEventListener('click', (event) => {
    if (event.target === deleteProductModal) closeDeleteModal();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && deleteProductModal && !deleteProductModal.hidden) closeDeleteModal();
  });
  deleteProductForm?.addEventListener('submit', (event) => {
    if (deleteConfirmStep.hidden) {
      event.preventDefault();
      return;
    }
    confirmDeleteValue.value = '1';
  });

  searchInput?.addEventListener('input', () => {
    const query = searchInput.value.trim().toLowerCase();
    if (!query) { searchResults.classList.remove('show'); searchResults.innerHTML = ''; return; }
    const matches = products.filter(([name]) => name.toLowerCase().includes(query));
    searchResults.innerHTML = matches.length ? matches.map(([name, price]) => `<button type="button"><span>${name}</span><b>${price}</b></button>`).join('') : '<p class="no-results">No matching products</p>';
    searchResults.classList.add('show');
  });

  posSearch?.addEventListener('input', () => {
    const value = posSearch.value.trim().toLowerCase();
    posProducts.forEach((button) => {
      const matches = !value || button.dataset.productName.toLowerCase().includes(value);
      button.style.display = matches ? 'grid' : 'none';
    });
  });

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-coming-soon], [data-placeholder-link]');
    if (trigger) {
      event.preventDefault();
    }
    if (!event.target.closest('.search-box')) searchResults?.classList.remove('show');
    if (window.innerWidth <= 760 && !event.target.closest('.sidebar') && !event.target.closest('#menuToggle')) sidebar?.classList.remove('open');
  });

  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); searchInput?.focus(); }
  });

  if (cartList) renderCart();
  if (cashReceived) updateTotals();

  const shopCartCount = document.getElementById('shopCartCount');
  const bundleSearch = document.getElementById('bundleSearch');
  const cartCheckoutForm = document.getElementById('cartCheckoutForm');
  const buyerCartList = document.querySelector('.buyer-cart-list');
  const buyerCartTotal = document.getElementById('buyerCartTotal');
  const buyerCartNotice = document.getElementById('buyerCartNotice');
  let buyerCartAlertTimeout;
  const deliverDifferentAddress = document.getElementById('deliverDifferentAddress');
  const alternativeAddressForm = document.getElementById('alternativeAddressForm');
  const defaultAddressEntry = document.getElementById('defaultAddressEntry');
  const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);

  function showBuyerCartAlert(message, isError = false) {
    if (!buyerCartNotice) return;
    buyerCartNotice.querySelector('.buyer-cart-alert-message').textContent = message;
    buyerCartNotice.classList.toggle('is-error', isError);
    buyerCartNotice.querySelector('.buyer-cart-alert-icon').textContent = isError ? '!' : '✓';
    buyerCartNotice.hidden = false;
    window.clearTimeout(buyerCartAlertTimeout);
    buyerCartAlertTimeout = window.setTimeout(() => { buyerCartNotice.hidden = true; }, 4500);
  }

  buyerCartNotice?.querySelector('.buyer-cart-alert-close')?.addEventListener('click', () => {
    buyerCartNotice.hidden = true;
    window.clearTimeout(buyerCartAlertTimeout);
  });

  function syncDeliveryAddressForms() {
    const useAlternative = Boolean(deliverDifferentAddress?.checked);
    if (alternativeAddressForm) {
      alternativeAddressForm.hidden = !useAlternative;
      alternativeAddressForm.querySelectorAll('input').forEach((input) => {
        if (input.name !== 'save_alternative_address') {
          input.disabled = !useAlternative;
          input.required = useAlternative && input.name !== 'alt_details';
        }
      });
    }
    if (defaultAddressEntry) {
      defaultAddressEntry.hidden = useAlternative;
      defaultAddressEntry.querySelectorAll('input').forEach((input) => {
        input.disabled = useAlternative;
      });
    }
  }

  deliverDifferentAddress?.addEventListener('change', syncDeliveryAddressForms);
  syncDeliveryAddressForms();

  async function updateBuyerCart(action, bundleId, quantity) {
    const response = await fetch('buyer-cart-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      body: JSON.stringify({ action, bundle_id: Number(bundleId), quantity })
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Could not update your cart.');
    if (shopCartCount) shopCartCount.textContent = result.count;
    return result.cart;
  }

  async function refreshCartCount() {
    if (!shopCartCount) return;
    try {
      const response = await fetch('buyer-cart-api.php');
      const result = await response.json();
      if (response.ok) shopCartCount.textContent = result.count;
    } catch (error) {
      showBuyerCartAlert('Cart could not be loaded. Refresh to try again.', true);
    }
  }

  function renderBuyerCart(items) {
    if (!buyerCartList) return;
    const cartFooter = document.querySelector('.buyer-cart-footer');
    if (!items.length) {
      buyerCartList.innerHTML = '<div class="buyer-empty-state"><h2>Your cart is empty</h2><p>Browse the available bundles and add your favorites.</p><a class="shop-checkout buyer-link-button" href="bundles.php">Shop bundles</a></div>';
      if (cartFooter) cartFooter.hidden = true;
      if (buyerCartTotal) buyerCartTotal.textContent = formatMoney(0);
      return;
    }
    if (cartFooter) cartFooter.hidden = false;
    const selectedIds = new Set([...buyerCartList.querySelectorAll('input[name="bundle_ids[]"]:checked')].map((input) => Number(input.value)));
    buyerCartList.innerHTML = items.map((item) => `
      <article class="buyer-cart-item" data-cart-item data-bundle-id="${item.bundle_id}" data-unit-price="${item.price}">
        <label class="cart-select"><input type="checkbox" name="bundle_ids[]" value="${item.bundle_id}" ${selectedIds.has(Number(item.bundle_id)) ? 'checked' : ''}><span class="sr-only">Select ${escapeHtml(item.name)}</span></label>
        ${item.image && item.image.includes('/') ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="buyer-item-image">` : '<div class="buyer-item-image buyer-image-placeholder" aria-hidden="true">GB</div>'}
        <div class="buyer-cart-item-info"><h2>${escapeHtml(item.name)}</h2><span>${formatMoney(item.price)} each</span></div>
        <div class="cart-quantity-control"><button type="button" data-cart-action="decrease" aria-label="Decrease quantity">−</button><span data-cart-quantity>${item.quantity}</span><button type="button" data-cart-action="increase" aria-label="Increase quantity">+</button></div>
        <strong class="cart-item-subtotal">${formatMoney(item.price * item.quantity)}</strong><button type="button" class="shop-remove" data-cart-action="remove" aria-label="Remove ${escapeHtml(item.name)}">×</button>
      </article>`).join('');
    updateSelectedCartTotal();
  }

  function updateSelectedCartTotal() {
    if (!buyerCartList || !buyerCartTotal) return;
    const total = [...buyerCartList.querySelectorAll('input[name="bundle_ids[]"]:checked')].reduce((sum, input) => {
      const row = input.closest('[data-cart-item]');
      const unit = Number(row.dataset.unitPrice || 0);
      const quantity = Number(row.querySelector('[data-cart-quantity]').textContent);
      return sum + unit * quantity;
    }, 0);
    buyerCartTotal.textContent = formatMoney(total);
  }

  document.querySelectorAll('.shop-add').forEach((button) => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await updateBuyerCart('add', button.dataset.bundleId);
        showBuyerCartAlert('Your bundle is saved in your cart.');
      } catch (error) {
        showBuyerCartAlert(error.message, true);
      } finally {
        button.disabled = false;
      }
    });
  });

  buyerCartList?.addEventListener('click', async (event) => {
    const target = event.target.closest('[data-cart-action]');
    if (!target) return;
    const row = target.closest('[data-cart-item]');
    const id = Number(row.dataset.bundleId);
    const quantity = Number(row.querySelector('[data-cart-quantity]').textContent);
    const action = target.dataset.cartAction;
    try {
      const items = await updateBuyerCart(action === 'remove' || (action === 'decrease' && quantity === 1) ? 'remove' : 'set', id, action === 'increase' ? quantity + 1 : quantity - 1);
      renderBuyerCart(items);
    } catch (error) {
      window.alert(error.message);
    }
  });

  buyerCartList?.addEventListener('change', updateSelectedCartTotal);
  bundleSearch?.addEventListener('input', () => {
    const query = bundleSearch.value.trim().toLowerCase();
    document.querySelectorAll('[data-bundle-card]').forEach((card) => {
      card.hidden = query !== '' && !card.dataset.bundleSearch.includes(query);
    });
  });
  cartCheckoutForm?.addEventListener('submit', (event) => {
    if (!buyerCartList?.querySelector('input[name="bundle_ids[]"]:checked')) {
      event.preventDefault();
      window.alert('Select at least one bundle to check out.');
    }
  });
  refreshCartCount();
});
