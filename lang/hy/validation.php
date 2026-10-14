<?php

/*
|--------------------------------------------------------------------------
| Armenian validation messages
|--------------------------------------------------------------------------
|
| The admin panel is Armenian, so an English "The բառ field is required."
| broke the flow. Only the rules this project actually uses are translated;
| any other message falls back to the framework's English wording.
|
*/

return [
    'required' => '«:attribute» դաշտը պարտադիր է։',
    'string' => '«:attribute»-ը պետք է տեքստ լինի։',
    'integer' => '«:attribute»-ը պետք է ամբողջ թիվ լինի։',
    'numeric' => '«:attribute»-ը պետք է թիվ լինի։',
    'boolean' => '«:attribute»-ը պետք է այո/ոչ արժեք ունենա։',
    'array' => '«:attribute»-ը պետք է ցանկ լինի։',
    'file' => '«:attribute»-ը պետք է ֆայլ լինի։',
    'unique' => '«:attribute»-ն արդեն զբաղված է։',
    'regex' => '«:attribute»-ի ձևաչափը սխալ է։',
    'mimetypes' => '«:attribute»-ի ֆայլի տեսակը չի թույլատրվում։',
    'mimes' => '«:attribute»-ը պետք է լինի այս տեսակներից՝ :values։',
    'min' => [
        'numeric' => '«:attribute»-ը պետք է լինի առնվազն :min։',
        'string' => '«:attribute»-ը պետք է ունենա առնվազն :min նշան։',
        'array' => '«:attribute»-ը պետք է ունենա առնվազն :min տարր։',
        'file' => '«:attribute»-ը պետք է լինի առնվազն :min կիլոբայթ։',
    ],
    'max' => [
        'numeric' => '«:attribute»-ը չպետք է գերազանցի :max-ը։',
        'string' => '«:attribute»-ը չպետք է գերազանցի :max նշանը։',
        'array' => '«:attribute»-ը չպետք է ունենա ավելի քան :max տարր։',
        'file' => '«:attribute»-ը չպետք է գերազանցի :max կիլոբայթը։',
    ],
];
