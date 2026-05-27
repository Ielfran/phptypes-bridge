<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Support;

use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Schema\DtoSchema;
use PHPTypeS\Bridge\Schema\EndpointSchema;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\EnumTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\PropertySchema;

final class SchemaFactory
{
    
    public static function make(): ApiSchema
    {
        $schema = new ApiSchema();

        $statusEnum = new EnumTypeNode(
            fqcn: 'App\\Enums\\UserStatus',
            backingType: 'string',
            cases: ['Active' => 'active', 'Inactive' => 'inactive', 'Banned' => 'banned'],
        );
        $schema->addEnum($statusEnum);

        $addressDto = new DtoSchema(
            fqcn: 'App\\DTOs\\AddressDto',
            shortName: 'AddressDto',
            properties: [
                new PropertySchema('street', new ScalarTypeNode('string')),
                new PropertySchema('city', new ScalarTypeNode('string')),
                new PropertySchema('postcode', new ScalarTypeNode('string', nullable: true), optional: true),
            ],
        );
        $schema->addDto($addressDto);

        $userDto = new DtoSchema(
            fqcn: 'App\\DTOs\\UserDto',
            shortName: 'UserDto',
            properties: [
                new PropertySchema('id', new ScalarTypeNode('int')),
                new PropertySchema('email', new ScalarTypeNode('string')),
                new PropertySchema('status', $statusEnum),
                new PropertySchema('address', new RefTypeNode('App\\DTOs\\AddressDto')),
                new PropertySchema(
                    'bio',
                    new ScalarTypeNode('string', nullable: true),
                    optional: true,
                    docComment: 'User biography text',
                ),
            ],
        );
        $schema->addDto($userDto);

       $createRequest = new DtoSchema(
            fqcn: 'App\\DTOs\\CreateUserRequest',
            shortName: 'CreateUserRequest',
            properties: [
                new PropertySchema('email', new ScalarTypeNode('string')),
                new PropertySchema('name', new ScalarTypeNode('string')),
                new PropertySchema('bio', new ScalarTypeNode('string', nullable: true), optional: true),
            ],
        );
        $schema->addDto($createRequest);

        $schema->addEndpoint(new EndpointSchema(
            name: 'getUser',
            httpMethod: 'GET',
            path: '/api/v1/users/{id}',
            controllerFqcn: 'App\\Controllers\\UserController',
            methodName: 'show',
            requestType: null,
            responseType: new RefTypeNode('App\\DTOs\\UserDto'),
            pathParams: ['id'],
        ));

        $schema->addEndpoint(new EndpointSchema(
            name: 'createUser',
            httpMethod: 'POST',
            path: '/api/v1/users',
            controllerFqcn: 'App\\Controllers\\UserController',
            methodName: 'store',
            requestType: new RefTypeNode('App\\DTOs\\CreateUserRequest'),
            responseType: new RefTypeNode('App\\DTOs\\UserDto'),
            pathParams: [],
        ));

        $schema->addEndpoint(new EndpointSchema(
            name: 'listUsers',
            httpMethod: 'GET',
            path: '/api/v1/users',
            controllerFqcn: 'App\\Controllers\\UserController',
            methodName: 'index',
            requestType: null,
            responseType: new ArrayTypeNode(new RefTypeNode('App\\DTOs\\UserDto')),
            pathParams: [],
        ));

        $schema->addEndpoint(new EndpointSchema(
            name: 'deleteUser',
            httpMethod: 'DELETE',
            path: '/api/v1/users/{id}',
            controllerFqcn: 'App\\Controllers\\UserController',
            methodName: 'destroy',
            requestType: null,
            responseType: null,
            pathParams: ['id'],
        ));

        return $schema;
    }
}
