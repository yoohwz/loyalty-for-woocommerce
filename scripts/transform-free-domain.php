<?php
// Build-time tokenizer only; never loaded by the plugin.
// Replace only the literal domain argument of global WordPress translation calls.
$source = stream_get_contents(STDIN);
$tokens = token_get_all($source, TOKEN_PARSE);
foreach ($tokens as $token) {
    if (is_array($token) && $token[0] === T_NAMESPACE) {
        fwrite(STDERR, "Namespaced source requires a separately reviewed transformation.\n");
        exit(1);
    }
}
$domain_index = array('__' => 1, '_e' => 1, '_x' => 2, '_ex' => 2, '_n' => 3, '_nx' => 4,
    '_n_noop' => 2, '_nx_noop' => 3, 'esc_html__' => 1, 'esc_html_e' => 1,
    'esc_html_x' => 2, 'esc_attr__' => 1, 'esc_attr_e' => 1, 'esc_attr_x' => 2);
function free_token_text($token) { return is_array($token) ? $token[1] : $token; }
function free_significant($tokens, $index, $direction) {
    for ($i = $index + $direction; $i >= 0 && $i < count($tokens); $i += $direction) {
        if (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { continue; }
        return $i;
    }
    return null;
}
$count = 0;
for ($i = 0; $i < count($tokens); $i++) {
    $token = $tokens[$i];
    if (!is_array($token) || $token[0] !== T_STRING || !isset($domain_index[strtolower($token[1])])) { continue; }
    $previous = free_significant($tokens, $i, -1);
    if ($previous !== null && (in_array(free_token_text($tokens[$previous]), array('->', '?->', '::', '\\'), true) ||
        (is_array($tokens[$previous]) && in_array($tokens[$previous][0], array(T_FUNCTION, T_NEW), true)))) { continue; }
    $open = free_significant($tokens, $i, 1);
    if ($open === null || free_token_text($tokens[$open]) !== '(') { continue; }
    $arguments = array(); $argument = array(); $depth = 0; $closed = false;
    for ($j = $open + 1; $j < count($tokens); $j++) {
        $text = free_token_text($tokens[$j]);
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) { $depth++; }
        // Punctuation inside quoted strings/comments is a token, not delimiter.
        if (!is_array($tokens[$j])) {
            if (in_array($text, array('(', '[', '{'), true)) { $depth++; }
            if (in_array($text, array(')', ']', '}'), true)) {
                if ($depth === 0 && $text === ')') { $arguments[] = $argument; $closed = true; break; }
                $depth--;
            }
            if ($depth === 0 && $text === ',') { $arguments[] = $argument; $argument = array(); continue; }
        }
        if (!is_array($tokens[$j]) || !in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) { $argument[] = $j; }
    }
    $index = $domain_index[strtolower($token[1])];
    if (!$closed || !isset($arguments[$index]) || count($arguments[$index]) !== 1) { continue; }
    $position = $arguments[$index][0];
    $literal = $tokens[$position];
    if (is_array($literal) && $literal[0] === T_CONSTANT_ENCAPSED_STRING &&
        in_array($literal[1], array("'wc-loyalty'", '"wc-loyalty"'), true)) {
        $quote = $literal[1][0];
        $tokens[$position][1] = $quote . 'loyalty-for-woocommerce' . $quote;
        $count++;
    }
}
$output = '';
foreach ($tokens as $token) { $output .= free_token_text($token); }
echo json_encode(array('source' => base64_encode($output), 'replacements' => $count), JSON_THROW_ON_ERROR);
