(function (wp) {
    'use strict';
    var titles = { 'points-balance': 'Points balance', 'points-history': 'Points history', 'level-progress': 'Loyalty level', 'ways-to-earn': 'Ways to earn', 'referral-link': 'Referral link' };
    Object.keys(titles).forEach(function (kind) {
        wp.blocks.registerBlockType('loyf/' + kind, {
            apiVersion: 3, title: wp.i18n.__(titles[kind], 'loyalty-for-woocommerce'), category: 'widgets', icon: 'awards', supports: { html: false, customClassName: false },
            edit: function () { return wp.element.createElement('div', wp.blockEditor.useBlockProps(), wp.element.createElement(wp.serverSideRender, { block: 'loyf/' + kind })); },
            save: function () { return null; }
        });
    });
}(window.wp));
