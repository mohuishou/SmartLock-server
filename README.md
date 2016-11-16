# SmartLock-server

> SmartLock后端程序，一共两个进程，进程1使用WebSocket协议与手机App通信，进程2使用TCP协议，与智能硬件通信



## 数据约定

### 数据类型

```
{
    "lock_id": "12345",
    "is_stolen": "1",
    "is_low_battery": "0",
    "lon": "123.123",
    "lat": "143.11"
}
```

### 数据说明

| 字段 | 说明 |
|:----:|:----:|
| lock_id | 设备id |
| is_stolen | 是否被盗;1:被盗;0:未被盗 |
| is_low_battery | 是否没电;1:没电;0:有电 |
| lon | 经度 |
| lat | 纬度 |


