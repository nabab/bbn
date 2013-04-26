<?php
return [
    'tag' => 'input',
    'attr' => [
        'type' => 'date',
        'maxlength' => 10,
        'size' => 10,
    ],
    'widget' => [
        'options' => [
            'format' => 'yyyy-MM-dd',
        ],
        'name' => 'kendoDatePicker',
    ],
];
?>