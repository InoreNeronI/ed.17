<?php

namespace Helper;

/**
 * Class TranslationsHelper.
 */
class TranslationsHelper
{
    /** @var array */
    private static $langISOCodes;

    /** @var array */
    private static $localizedMsg;

    /**
     * @param array  $messages
     * @param array  $data
     * @param array  $langISOCodes
     * @param string $defaultLang
     *
     * @return array
     */
    public static function localize(array $messages, array $data = [], $langISOCodes = ['en'], $defaultLang = 'en')
    {
        static::$langISOCodes = $langISOCodes;
        $messages = static::localizeMessages(array_merge($messages, $data));
        isset($messages['lang']) ?: $messages['lang'] = $defaultLang;

        return $messages;
    }

    /**
     * @param array       $messages
     * @param string|null $lang
     * @param string|null $stage
     *
     * @return array
     */
    private static function localizeMessages(array $messages, $lang = null, $stage = null)
    {
        $lang = is_null($lang) && isset($messages['lang']) ? $messages['lang'] : $lang;
        $stage = is_null($stage) && isset($messages['stage']) ? $messages['stage'] : $stage;
        foreach ($messages as $key => $message) {
            if ($key === 'actions'/* && !is_null($stage)*/) {
                foreach ($message as $k => $v) {
                    if (strpos($k, $stage) === false) {
                        unset($messages[$key][$k]);
                    }
                }
                $messages[$key] = static::localizeMessages($messages[$key], $lang, $stage);
            } elseif (is_array($message)) {
                static::$localizedMsg = [];
                foreach ($message as $msg_key => $msg_value) {
                    if (($msg = static::localizeMessage($msg_key, $msg_value, $lang, $stage)) !== false) {
                        $messages[$key] = $msg;
                    }
                }
                empty(static::$localizedMsg) ?: $messages[$key] = implode(' / ', static::$localizedMsg);
            } elseif (($msg = static::localizeMessage($key, $message, $lang, $stage)) !== false) {
                $messages = $msg;
            }
        }

        return $messages;
    }

    /**
     * @param string       $msg_key
     * @param array|string $msg_value
     * @param string|null  $lang
     * @param string|null  $stage
     *
     * @return array|false
     */
    private static function localizeMessage($msg_key, $msg_value, $lang = null, $stage = null)
    {
        if ($msg_key === $lang/* && !empty($msg_value)*/) {
            return $msg_value;
        } elseif ($msg_key === $stage && is_array($msg_value)) {
            return static::localizeMessages($msg_value, $lang);
        } elseif (is_null($lang) && in_array($msg_key, static::$langISOCodes) && !empty($msg_value)) {
            static::$localizedMsg[] = $msg_value;
        }

        return false;
    }

    /**
     * @param array       $items
     * @param string      $filename
     * @param string|null $break_table
     * @param string|null $prefix
     *
     * @return array
     */
    private static function parseFields(array $items, $filename = 'index', $break_table = null, $prefix = null)
    {
        $fields = [];
        $config = parseConfig(getenv('CONFIG_DIR'), $filename);
        foreach ($items as $name => $value) {
            if (array_key_exists($name, $config)) {
                $fields[$name] = static::mapFields($config[$name], $break_table, $prefix);
            }
        }

        return $fields;
    }

    /**
     * @param array       $tables
     * @param string|null $break_table
     * @param string|null $prefix
     *
     * @return array
     */
    private static function mapFields(array $tables, $break_table = null, $prefix = null)
    {
        $fields = [];
        foreach ($tables as $table_name => $table_field) {
            $name = $prefix.$table_name;
            if (is_array($table_field)) {
                $fields = array_merge($fields, static::mapFields($table_field, $break_table, $name));
            } else {
                $fields[$name] = $table_field;
            }
            if ($table_name === $break_table) {
                return $fields;
            }
        }

        return $fields;
    }
}
