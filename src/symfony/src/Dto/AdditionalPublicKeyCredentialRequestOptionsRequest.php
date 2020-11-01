<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Webauthn\Bundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Webauthn\PublicKeyCredentialRequestOptions;

final class AdditionalPublicKeyCredentialRequestOptionsRequest
{
    /**
     * @var null|string
     *
     * @Assert\Type("string")
     * @Assert\Choice({PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_DISCOURAGED, PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED, PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED})
     */
    public $userVerification;

    /**
     * @var array|null
     */
    public $extensions;
}
