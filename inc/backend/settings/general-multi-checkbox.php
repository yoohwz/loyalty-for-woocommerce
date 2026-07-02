<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Multi_Checkbox {
    public function __construct() {
        add_action('woocommerce_admin_field_yol_multi_checkbox', [$this, 'yol_multi_checkbox']);
    }

    public function yol_multi_checkbox($value) {
        $option_value = get_option($value['id'], $value['default']) ?: array();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html($value['title']); ?>
                </label>
                <?php if ($value['desc_tip']) : ?>
                    <?php echo wp_kses_post( wc_help_tip( $value['desc_tip'] ) ); ?>
                <?php endif; ?>
            </th>
            <td class="forminp forminp-checkbox">
                <?php foreach ($value['options'] as $key => $description) : ?>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo esc_html($description); ?></span></legend>
                        <label for="<?php echo esc_attr($value['id'] . '_' . $key); ?>">
                            <input type="checkbox" name="<?php echo esc_attr($value['id']); ?>[]" id="<?php echo esc_attr($value['id'] . '_' . $key); ?>" value="<?php echo esc_attr($key); ?>" <?php checked( in_array($key, $option_value), true ); ?> />
                            <?php echo esc_html($description); ?>
                        </label><br>
                    </fieldset>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php
    }           
}

new YOSWC_Loyalty_Settings_Multi_Checkbox();
