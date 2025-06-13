# content-repository-webhooks

## Authentication 

TBD


Run:

    ./flow hashtoken:createhashtoken --roleNames Neos.Neos:RestrictedEditor


## Available Endpoints

### Node

#### Update

```http
POST /neosidekick/n8n/node/update
Content-Type: application/json
Cookie: Neos_Session=

{
  "nodeContextPath": "/sites/site/main/node-blj8xfx5l21m9@user-admin;language=en_US",
  "updatedProperties": {
    "title": "My Title"
  }
}
```

### Workspace

#### Create

```http
POST /neosidekick/n8n/workspace/create
Content-Type: application/json
Cookie: Neos_Session=

{
  "workspaceName": "my-new-workspace"
}
```
