# EnergyLocal backend

Club, account, tariff and data administration

## API

### Tariff

#### List Tariffs

- **GET** `/tariff/list.json?clubid=1` (PUBLIC)
- **GET** `/tariff/list?clubid=1`
- **Description:** Returns JSON list of tariffs or HTML view of tariffs.

#### Add a New Tariff

- **POST** `/tariff/create`
- **Request Body:** club=1&name=MyTariff
- **Response:** JSON success or fail

#### Delete Tariff

- **GET** `/tariff/delete?id=1` 
- **Request Parameters:** id=1
- **Response:** JSON success or fail

#### List Tariff Periods

- **GET** `/tariff/periods?id=1` (PUBLIC)
- **Description:** Returns JSON list of periods.

#### Add Period

- **POST** `/tariff/addperiod`
- **Request Body:** tariffid=1&name=MyPeriod&weekend=0&start=0&generator=15&import=20&color=#000
- **Response:** JSON success or fail

#### Delete Period

- **GET** `/tariff/deleteperiod?tariffid=1&index=0`
- **Request Parameters:** tariffid=1&index=0
- **Response:** JSON success or fail

#### Save Period

- **POST** `/tariff/saveperiod`
- **Request Body:** tariffid=1&index=0&name=MyPeriod&weekend=0&start=0&generator=15&import=20&color=#000
- **Response:** JSON success or fail
