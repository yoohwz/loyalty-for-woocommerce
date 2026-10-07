/* Native Woo Slot/Fill and Store API cart-extension adapter. */
(function (wp, wc) {
    'use strict';
    if (!wp || !wc || !wc.blocksCheckout || !wc.wcBlocksData) return;
    const { createElement: h, useState, useRef } = wp.element;
    const { __, sprintf } = wp.i18n;
    const { ExperimentalDiscountsMeta, extensionCartUpdate } = wc.blocksCheckout;
    if (!ExperimentalDiscountsMeta || !extensionCartUpdate) return;
    function operationId() {
        if (window.crypto.randomUUID) return window.crypto.randomUUID();
        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }
    function Redemption() {
        const data = wp.data.useSelect(select => {
            const cart = select(wc.wcBlocksData.CART_STORE_KEY).getCartData();
            return cart.extensions && cart.extensions['loyf-redemption'];
        }, []);
        const [points, setPoints] = useState('');
        const [busy, setBusy] = useState(false);
        const [error, setError] = useState('');
        const pending = useRef(null);
        if (!data || (!data.enabled && !data.message)) return null;
        function update(action) {
            if (busy) return;
            if (action === 'apply' && !/^[0-9]{1,8}$/.test(points)) {
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
        return h('div', { className: 'loyf-blocks-redemption' },
            h('p', null, sprintf(__('Available points: %d', 'loyalty-for-woocommerce'), data.available)),
            data.earned > 0 && h('p', null, sprintf(__('You will earn %d points with this purchase.', 'loyalty-for-woocommerce'), data.earned)),
            data.message && h('p', { role: 'status' }, data.message),
            data.selected > 0 && h('p', null, sprintf(__('%d points applied: %s', 'loyalty-for-woocommerce'), data.selected, data.discount)),
            data.enabled && h(wp.components.TextControl, { label: __('Points to apply', 'loyalty-for-woocommerce'), type: 'number', min: data.minimum, max: data.available, value: points, disabled: busy || !!pending.current, onChange: setPoints }),
            data.enabled && h(wp.components.Button, { variant: 'secondary', disabled: busy, onClick: () => update('apply') }, pending.current ? __('Retry points update', 'loyalty-for-woocommerce') : __('Apply points', 'loyalty-for-woocommerce')),
            data.selected > 0 && h(wp.components.Button, { variant: 'tertiary', disabled: busy || !!pending.current, onClick: () => update('remove') }, __('Remove points', 'loyalty-for-woocommerce')),
            error && h('p', { role: 'alert' }, error));
    }
    wp.plugins.registerPlugin('loyf-redemption', { scope: 'woocommerce-checkout', render: () => h(ExperimentalDiscountsMeta, null, h(Redemption)) });
})(window.wp, window.wc);
