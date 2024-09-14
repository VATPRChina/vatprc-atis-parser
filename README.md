# vatprc-atis-parser

## Endpoint

```
HTTP GET /datis.php?...
```

## Parameters

| Name     | Description                                                                           |
| -------- | ------------------------------------------------------------------------------------- |
| acdm     | (optional) `1` for enabling A-CDM on the airport other than designated madantory ones |
| adelv    | (optional) aerodrome elevation in meters                                              |
| apptype  | approach type, such as `ILS`                                                          |
| arr      | arrival runway identifier, separated by `,`                                           |
| atistype | (optional) ATIS type, `D` for dep, `A` for arr and other for a combined               |
| dep      | departure runway identifier, separated by `,`                                         |
| info     | ATIS identifier, such as `A`                                                          |
| metar    | METAR of the target airport                                                           |
| NOTAM    | (optional) any NOTAM                                                                  |
