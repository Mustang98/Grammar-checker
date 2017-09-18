<?php 
include "word_correction.php";

// Сorrespondence between Cyrillic and Latin symbols
// Latin:       "abcdefghijklmnopqrstuvwxyz"

$en_ru_layout = "фисвуапршолдьтщзйкыегмцчня";

// Converts mb string to an array of symbols
function mb_string_to_symbols($mb_string) {
    return preg_split('//u', $mb_string, -1, PREG_SPLIT_NO_EMPTY);
}

// Checks whether the multibyte symbol is Latin letter or not
function mb_is_latin($symbol) {
    return ($symbol >= "a" && $symbol <= "z") ||
        ($symbol >= "A" && $symbol <= "Z");
}

// Checks whether the multibyte symbol is Cyrillic letter or not
function mb_is_cyrillic($symbol) {
    return ($symbol >= "а" && $symbol <= "я" || $symbol == "ё") ||
        ($symbol >= "А" && $symbol <= "Я" || $symbol == "Ё");
}

// Checks whether the multibyte symbol is digit or not
function mb_is_digit($symbol) {
    return ($symbol >= "0" && $symbol <= "9");
}

// Checks whether the multibyte symbol alphanumerical or not
function mb_is_alphanumerical($symbol) {
    return mb_is_cyrillic($symbol) || 
        mb_is_digit($symbol) ||
        mb_is_latin($symbol);
}

define("RESULT_OK", "OK"); // The word is correct
define("RESULT_RP", "RP"); // The word was incorrect but replaced with the correct one
define("RESULT_NF", "NF"); // The word was incorrect and the replacement wasn't found

// Process the word and tries to correct it if needed
// Returns source or corrected word. Status is being written to $result (see possible statuses above) 
function process_word($word, &$result) {
    global $en_ru_layout;
    
    $word = mb_strtolower($word);
    $symbols = mb_string_to_symbols($word);
    $has_latin_letter = false;
    $has_cyrillic_letter = false;
    foreach ($symbols as $symbol) {
        if (mb_is_cyrillic($symbol)) {
            $has_cyrillic_letter = true;
        } else if (mb_is_latin($symbol)) {
            $has_latin_letter = true;
        }
        if ($has_cyrillic_letter && $has_latin_letter) { 
            break;
        }
    }
    // Only digits - nothing to correct
    if (!$has_cyrillic_letter && !$has_latin_letter) {
        $result = RESULT_OK;
        return $word;
    }
    // Both Latin and Cyrillyc letters
    if ($has_cyrillic_letter && $has_latin_letter) {
        $result = RESULT_NF;
        return $word;
    }

    $leading_digits = "";
    $ending_digits_arr = [];
    $ending_digits = "";

    for ($i = 0; mb_is_digit($symbols[$i]); ++$i) { // End will never be reached since there is at least 
        $leading_digits .= $symbols[$i]; // one non-digit symbol
    }

    for ($i = count($symbols) - 1; mb_is_digit($symbols[$i]); --$i) {
        $ending_digits_arr[] = $symbols[$i];
    }
    $ending_digits = implode("", array_reverse($ending_digits_arr));
    $word = mb_substr($word, 
                      mb_strlen($leading_digits), 
                      mb_strlen($word) - mb_strlen($leading_digits) - mb_strlen($ending_digits));
    $symbols = mb_string_to_symbols($word);
    if ($has_latin_letter) {
        foreach ($symbols as &$symbol) {
            if (mb_is_latin($symbol)) {
                $letter_order = ord($symbol) - ord("a");
                $symbol = mb_substr($en_ru_layout, $letter_order, 1);
            }
        }
        $cyrillic_word = implode($symbols);
        if (word_exist($cyrillic_word)) {
            $result = RESULT_RP;
            return $leading_digits.mb_strtoupper($cyrillic_word).$ending_digits;
        } else {
            $result = RESULT_NF;
            return $leading_digits.$word.$ending_digits;
        }
    } else {
        if (word_exist($word)) {
            $result = RESULT_OK;
            return $leading_digits.$word.$ending_digits;
        }
        $corrected;
        $corrected_word = find_replacement($word, $corrected);
        if ($corrected) {
            $result = RESULT_RP;
            return $leading_digits.$corrected_word.$ending_digits;
        } else {
            $result = RESULT_NF;
            return $leading_digits.$word.$ending_digits;
        }
    }
}

// Splits message into words (continious alphanumerical sequences) and delimeters (other continious sequences)
function split_to_words($message, &$words, &$delimeters, &$start_from_word) {
    $words = [];
    $delimeters = [];
    $current_word = "";
    $current_delimeter = "";
    $mb_symbols = mb_string_to_symbols($message);
    $start_from_word = (count($mb_symbols) > 0 && mb_is_alphanumerical($mb_symbols[0]));
    foreach ($mb_symbols as $symbol) {
        if (mb_is_alphanumerical($symbol)) {
            if (!empty($current_delimeter)) {
                $delimeters[] = $current_delimeter;
                $current_delimeter = "";
            } 
            $current_word .= $symbol;
        } else {
            if (!empty($current_word)) {
                $words[] = $current_word;
                $current_word = "";
            } 
            $current_delimeter .= $symbol;
        }
    }
    if (!empty($current_word)) {
        $words[] = $current_word;
    } else if (!empty($current_delimeter)) {
        $delimeters[] = $current_delimeter;
    }
}

// Merges words inserting delimeters between them. It's guaranteed that words' count differs 
// from delimeters' count at most by 1 (since they were obtained from the correct message) 
// The word is placed firstly.
function merge_to_message(&$words, &$delimeters) {
    $words_count = count($words);
    $delimeters_count = count($delimeters);
    $message = "";
    for ($i = 0; $i < $words_count; ++$i) {
        $message .= $words[$i];
        if ($i < $delimeters_count) {
            $message .= $delimeters[$i];
        }
    }
    return $message;
}

// Processes message and returns the corrected one
function process_message($message, &$was_corrected) {
    $words = [];
    $processed_words = [];
    $delimeters = [];
    $start_from_word;
    
    split_to_words($message, $words, $delimeters, $start_from_word);
    
    foreach ($words as $word) {
        $processing_result;
        $processed_words[] = process_word($word, $processing_result);
        if ($processing_result == RESULT_RP) {
            $was_corrected = true;
        }
    }
    
    if (!$was_corrected) {
        return $message;
    }

    $corrected_message = "";

    if ($start_from_word) {
        $corrected_message = merge_to_message($processed_words, $delimeters);
    } else {
        $corrected_message = merge_to_message($delimeters, $processed_words);
    }
    	
    return $corrected_message;
}
?>