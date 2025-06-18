# NEOSidekick Lawnmower: your versatile toolchain for connecting AI Agents to Neos CMS

For our talk at the Neos Conference 2025, we came up with this package to experiment with connecting AI Agents to Neos CMS.
It helps, on the one hand to send webhooks for content changes in Neos CMS to AI Agents, 
and on the other hand to provide endpoints for AI Agents to interact with Neos CMS.

## Installation

```shell
composer require neosidekick/lawnmower
```

## Content Repository Webhooks

### Authentication 

Important: right now there is no authentication available for webhooks as it is a draft project. Feel free to come up with suggestions and PRs!

## Tool Endpoints

### Authentication

To use the tool endpoints, you need to create a hash token with the role `NEOSidekick.Lawnmower:TokenEditor`. 
This can be done using the command line interface:

```shell
./flow hashtoken:createhashtoken --roleNames NEOSidekick.Lawnmower:TokenEditor
```

Feel free to adjust the privileges of this role to your needs.

### General Endpoints

#### List tools

```http request
GET /neosidekick/lawnmower/tools/list
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{}
```

#### Ping

```http request
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{
  "name": "Ping",
  "arguments": {
    "param1": "Hello",
    "param2": "World"
  }
}
```

#### Get Sitemap

```http request
### Get the sitemap
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer 4FGl4St6UIK6lsFi3iliZrB6dv8Sbk47IitEuusUs1PV9snss0MkAKOiYnVNGt1y

{
  "name": "Sitemap",
  "arguments": {
    "dimension": {
      "language": ["en"]
    }
  }
}
```

### Workspace

#### Create a new workspace
```http request
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{
  "name": "CreateWorkspace",
  "arguments": {
    "workspaceName": "my-funny-workspace"
  }
}
```

### Publish and Delete Workspace

```http request
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{
  "name": "PublishAndDeleteWorkspace",
  "arguments": {
    "workspaceName": "my-funny-workspace"
  }
}
```

#### Discard and Delete Workspace

```http request
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{
  "name": "DiscardAndDeleteWorkspace",
  "arguments": {
    "workspaceName": "my-funny-workspace"
  }
}
```

### Nodes

#### Update Node Properties
```http request
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{
  "name": "UpdateNodeProperties",
  "arguments": {
    "nodeContextPath": {
      "nodePath": "/sites/site/main/node-blj8xfx5l21m9",
      "workspaceName": "my-funny-workspace",
      "dimensions": {
          "language": [
            "en_US"
          ]
      }
    },
    "updatedProperties": {
      "text": "<p>New text</p>"
    }
  }
}
```

#### Search in Nodes

```http request
POST /neosidekick/lawnmower/tools/call
Content-Type: application/json
Authorization: Bearer here-comes-your-bearer-token

{
  "name": "SearchInNodes",
  "arguments": {
    "term": "John Doe"
  }
}
```
