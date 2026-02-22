# RBAC Matrix

## Roles

| Role      | Description                    |
|-----------|--------------------------------|
| `admin`   | Full access, user management   |
| `manager` | CRUD contracts, закупки, payments, docs |
| `viewer`  | Read-only access               |

## Permissions Matrix

| Action                    | admin | manager | viewer |
|---------------------------|:-----:|:-------:|:------:|
| View contracts list       |  ✅   |   ✅    |   ✅   |
| View contract details     |  ✅   |   ✅    |   ✅   |
| Create contract           |  ✅   |   ✅    |   ❌   |
| Edit contract             |  ✅   |   ✅    |   ❌   |
| Delete contract           |  ✅   |   ❌    |   ❌   |
| Add payment               |  ✅   |   ✅    |   ❌   |
| Edit payment              |  ✅   |   ✅    |   ❌   |
| Delete payment            |  ✅   |   ❌    |   ❌   |
| Add contract stage        |  ✅   |   ✅    |   ❌   |
| Edit contract stage       |  ✅   |   ✅    |   ❌   |
| Delete contract stage     |  ✅   |   ❌    |   ❌   |
| Add invoice               |  ✅   |   ✅    |   ❌   |
| Edit invoice              |  ✅   |   ✅    |   ❌   |
| Delete invoice            |  ✅   |   ❌    |   ❌   |
| Add act                   |  ✅   |   ✅    |   ❌   |
| Edit act                  |  ✅   |   ✅    |   ❌   |
| Delete act                |  ✅   |   ❌    |   ❌   |
| Upload document           |  ✅   |   ✅    |   ❌   |
| Download document         |  ✅   |   ✅    |   ✅   |
| Delete document           |  ✅   |   ❌    |   ❌   |
| View procurements list    |  ✅   |   ✅    |   ✅   |
| View procurement details  |  ✅   |   ✅    |   ✅   |
| Create procurement        |  ✅   |   ✅    |   ❌   |
| Edit procurement          |  ✅   |   ✅    |   ❌   |
| Delete procurement        |  ✅   |   ❌    |   ❌   |
| Add commercial proposal   |  ✅   |   ✅    |   ❌   |
| Select winning proposal   |  ✅   |   ✅    |   ❌   |
| Delete proposal           |  ✅   |   ✅    |   ❌   |
| View audit log            |  ✅   |   ❌    |   ❌   |
| Manage users              |  ✅   |   ❌    |   ❌   |
| Export data               |  ✅   |   ✅    |   ✅   |

## Implementation

RBAC is enforced at the **Service** layer via `Session::hasRole()`:

```php
if (!$this->app->session()->hasRole('admin', 'manager')) {
    throw new ForbiddenException('Insufficient permissions');
}
```

The `AuthMiddleware` blocks unauthenticated access.
Role checks happen in services before mutations.
