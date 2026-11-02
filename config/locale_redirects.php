<?php

// URLs do site antigo em inglês (htdocs/en/*.php) => slug da página original em pt-BR.
// O 301 resolve para a tradução publicada; sem tradução, cai na home do idioma.
// Só é consultado quando o slug pedido não existe no idioma, então não conflita com slugs traduzidos iguais.

return [
    'en' => [
        'index' => 'home',
        'webdesign' => 'desenvolvimento-de-sites',
        'website-maintenance' => 'manutencao-de-sites',
        'contratar-manutencao' => 'manutencao-de-sites',
        'hosting' => 'hospedagem-de-sites',
        'full-stack-development' => 'software-sob-medida',
        'desenv-full-stack' => 'software-sob-medida',
        'ecommerce' => 'desenvolvimento-de-ecommerce',
        'portfolio' => 'cases',
        'signup' => 'contato',
        'contrato' => 'contato',
        'obrigado' => 'obrigado',
    ],
];
