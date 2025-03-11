<?php

return function () {
    try {
        $url = 'https://bonfitness.com.br/api/v1/auth/token';
        $init = curl_init($url);
        curl_setopt_array(
            $init,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ],
                CURLOPT_POSTFIELDS => [
                    'client_id' => 'Teste_48e494e4da0f8',
                    'client_secret' => '85a2a4b84f373def914e694034eac463e7f68c40',
                    'grant_type' => 'client_credentials',
                ]
            ]
        );
        $dados = json_decode(curl_exec($init), true);
        curl_close($init);
        return $dados['token_type'] . " " . $dados['access_token'] ;
    } catch (Exception $e) {
        return $e->getMessage();
    }
};