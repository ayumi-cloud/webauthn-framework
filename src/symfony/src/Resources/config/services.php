<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

use CBOR\Decoder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;
use Webauthn\AttestationStatement;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;
use Webauthn\CredentialRepository;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\TokenBinding;
use Webauthn\TokenBinding\TokenBindingHandler;

return function (ContainerConfigurator $container) {
    $container = $container->services()->defaults()
        ->private()
        ->autoconfigure()
        ->autowire();

    $container->set(AuthenticatorAttestationResponseValidator::class)
        ->public();
    $container->set(AuthenticatorAssertionResponseValidator::class)
        ->args([
            ref(CredentialRepository::class),
            ref(Decoder::class),
            ref(TokenBindingHandler::class),
            ref(ExtensionOutputCheckerHandler::class),
            ref('webauthn.cose.algorithm.manager')->nullOnInvalid(),
        ])
        ->public();

    $container->set(PublicKeyCredentialLoader::class)
        ->public();
    $container->set(PublicKeyCredentialCreationOptionsFactory::class)
        ->args([
            '%webauthn.creation_profiles%',
        ])
        ->public();
    $container->set(PublicKeyCredentialRequestOptionsFactory::class)
        ->args([
            '%webauthn.request_profiles%',
        ])
        ->public();

    $container->set(ExtensionOutputCheckerHandler::class);
    $container->set(AttestationStatement\AttestationObjectLoader::class);
    $container->set(AttestationStatement\AttestationStatementSupportManager::class);
    $container->set(AttestationStatement\NoneAttestationStatementSupport::class);
    $container->set(AttestationStatement\TPMAttestationStatementSupport::class);
    $container->set(AttestationStatement\FidoU2FAttestationStatementSupport::class);
    $container->set(AttestationStatement\AndroidKeyAttestationStatementSupport::class);
    $container->set(AttestationStatement\PackedAttestationStatementSupport::class)
        ->args([
            ref(Decoder::class),
            ref('webauthn.cose.algorithm.manager'),
        ]);

    $container->set(TokenBinding\IgnoreTokenBindingHandler::class);
    $container->set(TokenBinding\TokenBindingNotSupportedHandler::class);
};
