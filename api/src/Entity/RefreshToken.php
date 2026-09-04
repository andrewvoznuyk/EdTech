<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Action\LogoutAction;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[Entity]
#[Table(name: 'refresh_tokens')]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/token/refresh/logout',
            status: 204,
            controller: LogoutAction::class,
            input: false,
            output: false,
            read: false,
        )
    ]
)]
class RefreshToken extends BaseRefreshToken
{

}
