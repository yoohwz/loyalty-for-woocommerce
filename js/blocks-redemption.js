/* Native Woo Slot/Fill and Store API cart-extension adapter. */
(function (wp, wc) {
    'use strict';
    if (!wp || !wc || !wc.blocksCheckout || !wc.wcBlocksData) return;
    const { createElement: h, useState, useRef, useId } = wp.element;
    const { __, sprintf } = wp.i18n;
    const { ExperimentalDiscountsMeta, extensionCartUpdate } = wc.blocksCheckout;
    const { TextInput, Button, TotalsWrapper } = wc.blocksComponents;
    if (!ExperimentalDiscountsMeta || !extensionCartUpdate) return;
    function operationId() {
        if (window.crypto.randomUUID) return window.crypto.randomUUID();
        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }
    function Redemption({ context }) {
        const inputId = 'loyf-points-' + useId();
        const data = wp.data.useSelect(select => {
            const cart = select(wc.wcBlocksData.CART_STORE_KEY).getCartData();
            return cart.extensions && cart.extensions['loyf-redemption'];
        }, []);
        const [points, setPoints] = useState('');
        const [busy, setBusy] = useState(false);
        const [error, setError] = useState('');
        const pending = useRef(null);
        if (!data) return null;
        const showEarned = data.earned > 0 && (context === 'woocommerce/cart' ? data.show_earned_cart : context === 'woocommerce/checkout' && data.show_earned_checkout);
        if (!data.enabled && !data.message && !showEarned) return null;
        function update(action) {
            if (busy) return;
            if (!pending.current && action === 'apply' && !/^[0-9]{1,8}$/.test(points)) {
                setError(__('Enter a whole points amount.', 'loyalty-for-woocommerce')); return;
            }
            // A lost response retains the original immutable request until retry succeeds.
            if (!pending.current) pending.current = action === 'remove'
                ? { action, operation_id: data.operation_id }
                : { action, points, operation_id: operationId() };
            const request = pending.current;
            setBusy(true); setError('');
            extensionCartUpdate({ namespace: 'loyf-redemption', data: request })
                .then(() => { pending.current = null; })
                .catch(failure => { if (failure && failure.code === 'loyf_redemption_rejected' && failure.data && failure.data.status === 409) pending.current = null; setError(__('Points could not be updated. Retry the same request or refresh your cart.', 'loyalty-for-woocommerce')); })
                .finally(() => setBusy(false));
        }
        return h(TotalsWrapper, null, h('div', { className: 'loyf-blocks-redemption wc-block-components-totals-item', role: 'group', 'aria-label': __('Loyalty points', 'loyalty-for-woocommerce'), 'aria-busy': busy },
            data.enabled && h('p', null, h('strong', null, __('Loyalty points', 'loyalty-for-woocommerce'))),
            data.enabled && h('p', null, sprintf(__('Available points: %d', 'loyalty-for-woocommerce'), data.available)),
            data.enabled && h('div', { className: 'loyf-blocks-redemption__controls' },
                h('div', { className: 'loyf-blocks-redemption__field' }, h(TextInput, { id: inputId, label: __('Points to apply', 'loyalty-for-woocommerce'), type: 'number', inputMode: 'numeric', min: data.minimum, max: data.available, value: points, disabled: busy || !!pending.current, onChange: setPoints })),
                h(Button, { type: 'button', disabled: busy, onClick: () => update('apply') }, pending.current ? __('Retry points update', 'loyalty-for-woocommerce') : __('Apply points', 'loyalty-for-woocommerce'))),
            data.selected > 0 && h('div', { className: 'loyf-blocks-redemption__selection' },
                h('p', { role: 'status' }, sprintf(__('%d points applied: %s', 'loyalty-for-woocommerce'), data.selected, data.discount)),
                h(Button, { type: 'button', disabled: busy || !!pending.current, onClick: () => update('remove') }, __('Remove points', 'loyalty-for-woocommerce'))),
            showEarned && h('p', null, sprintf(__('You will earn %d points with this purchase.', 'loyalty-for-woocommerce'), data.earned)),
            data.message && h('p', { role: 'status' }, data.message),
            busy && h('p', { role: 'status' }, __('Updating points…', 'loyalty-for-woocommerce')),
            error && h('p', { role: 'alert' }, error)));
    }
    wp.plugins.registerPlugin('loyf-redemption', { scope: 'woocommerce-checkout', render: () => h(ExperimentalDiscountsMeta, null, h(Redemption)) });
})(window.wp, window.wc);
