-- 创建数据库
CREATE DATABASE IF NOT EXISTS e_commerce_db;
USE e_commerce_db;

-- 1. 角色表
CREATE TABLE Roles (
    RoleId CHAR(36) PRIMARY KEY,
    RoleName VARCHAR(50) NOT NULL UNIQUE
);

-- 2. 用户账号表
CREATE TABLE Users (
    UserId CHAR(36) PRIMARY KEY,
    RoleId CHAR(36),
    Email VARCHAR(255) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    ResetToken CHAR(36),
    ResetTokenExpiry DATETIME,
    CreatedDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (RoleId) REFERENCES Roles(RoleId)
);

ALTER TABLE `Users`add `IsActive` BOOLEAN default true after `PasswordHash`;
ALTER TABLE `Users` add `LastLogin` DATETIME default current_timestamp;
-- 3. 用户资料表
CREATE TABLE UserProfile (
    ProfileId CHAR(36) PRIMARY KEY,
    UserId CHAR(36) UNIQUE,
    FirstName VARCHAR(100),
    LastName VARCHAR(100),
    PhoneNumber VARCHAR(20),
    ProfilePhotoUrl VARCHAR(255),
    CreateDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdateDate DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserId) REFERENCES Users(UserId) ON DELETE CASCADE
);

-- 4. 用户地址表 (支持多地址)
CREATE TABLE Addresses (
    AddressId CHAR(36) PRIMARY KEY,
    UserId CHAR(36),
    RecipientName VARCHAR(100),
    PhoneNumber VARCHAR(20),
    FullAddress TEXT,
    IsDefault BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (UserId) REFERENCES Users(UserId) ON DELETE CASCADE
);

-- 5. 产品表
CREATE TABLE Products (
    ProductId CHAR(36) PRIMARY KEY,
    ProductName VARCHAR(255) NOT NULL,
    Description TEXT,
    Price DECIMAL(10, 2) NOT NULL,
    StockQuantity INT DEFAULT 0,
    CreateDate DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 5.1 产品分类表
CREATE TABLE `category` (
    CategoryId CHAR(36) NOT NULL,
    CategoryName VARCHAR(100),
    CategoryIcon VARCHAR(255) NULL,
    CreateDate DATETIME,
    PRIMARY KEY (CategoryId)
);

ALTER TABLE Products
ADD COLUMN CategoryId CHAR(36),
ADD CONSTRAINT fk_category
FOREIGN KEY (CategoryId) REFERENCES category(CategoryId) ON DELETE SET NULL;

-- 6. 产品多图片表 (一品多图)
CREATE TABLE ProductImages (
    ImageId CHAR(36) PRIMARY KEY,
    ProductId CHAR(36),
    ImageUrl VARCHAR(255) NOT NULL,
    IsPrimary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (ProductId) REFERENCES Products(ProductId) ON DELETE CASCADE
);

-- 7. 购物车表
CREATE TABLE Carts (
    CartId CHAR(36) PRIMARY KEY,
    UserId CHAR(36),
    ProductId CHAR(36),
    Quantity INT DEFAULT 1,
    AddedDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserId) REFERENCES Users(UserId) ON DELETE CASCADE,
    FOREIGN KEY (ProductId) REFERENCES Products(ProductId) ON DELETE CASCADE
);

-- 8. 订单总表
CREATE TABLE Orders (
    OrderId CHAR(36) PRIMARY KEY,
    UserId CHAR(36),
    AddressId CHAR(36), -- 引用下单时的地址ID
    TotalAmount DECIMAL(10, 2) NOT NULL,
    OrderStatus VARCHAR(50),
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    ShippingAddress TEXT, -- 存储下单当下的地址快照，防止原地址删除或修改
    FOREIGN KEY (UserId) REFERENCES Users(UserId),
    FOREIGN KEY (AddressId) REFERENCES Addresses(AddressId) ON DELETE SET NULL
);

-- 9. 订单明细表
CREATE TABLE OrderItems (
    OrderItemId CHAR(36) PRIMARY KEY,
    OrderId CHAR(36),
    ProductId CHAR(36),
    Quantity INT NOT NULL,
    UnitPrice DECIMAL(10, 2) NOT NULL, -- 下单时的成交单价
    FOREIGN KEY (OrderId) REFERENCES Orders(OrderId) ON DELETE CASCADE,
    FOREIGN KEY (ProductId) REFERENCES Products(ProductId)
);

INSERT INTO Roles (RoleId, RoleName) 
VALUES 
(UUID(), 'Admin'),
(UUID(), 'Member');

SELECT * FROM `Roles`;

INSERT INTO Users (UserId, RoleId, Email, PasswordHash, IsActive, CreatedDate)
VALUES (
    UUID(), 
    (SELECT RoleId FROM Roles WHERE RoleName = 'Admin' LIMIT 1), 
    'admin@zeng-store.com', 
    '$2a$12$A8KfNnzRUmAlZydzmuAL.OX85UH/oQRYVtJRHJXnPIz7gMfuc6H1S', -- 模拟的哈希密码
    TRUE, 
    NOW()
);

INSERT INTO Users (UserId, RoleId, Email, PasswordHash, IsActive, CreatedDate)
VALUES (
    UUID(), 
    (SELECT RoleId FROM Roles WHERE RoleName = 'Member' LIMIT 1), 
    'shaoxiclass@gmail.com', 
    '$2a$12$8piW6xU.mNQ47WvfmGLytOEwNrZHHm9hP/e7R9GZTSFSrHLpR6g0S', -- 模拟的哈希密码
    TRUE, 
    NOW()
);

SELECT * FROM `users`;