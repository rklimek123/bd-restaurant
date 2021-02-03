/* Restaurant
 * 
 * Project for Databases course. Oracle SQL database script.
 *
 * Rafal Klimek, index 418291
 * University of Warsaw, Faculty of Mathematics, Informatics, and Mechanics
 * 10.01.2021
 */

--TABLES--

DROP SEQUENCE iddish_seq;
DROP SEQUENCE idaddress_seq;
DROP SEQUENCE iduser_seq;
DROP SEQUENCE identry_seq;
DROP SEQUENCE idorder_seq;

DROP TABLE NeedIngredient;
DROP TABLE Ingredient;

DROP TABLE OrderEntries;
DROP TABLE "Order";
DROP TABLE Entry;
DROP TABLE Dish;
DROP TABLE "User";
DROP TABLE Address;

CREATE OR REPLACE PACKAGE const AS
    base_arrival CONSTANT INT := 20;
    delivery_price CONSTANT INT := 10;
END;
/

CREATE TABLE Ingredient
(
    name  VARCHAR2(50) PRIMARY KEY,
    stock INT DEFAULT ON NULL 0 CHECK (stock >= 0)
);

CREATE TABLE Dish
(
    id         INT PRIMARY KEY,
    name       VARCHAR2(100) NOT NULL,
    price      INT           NOT NULL CHECK (price >= 0),
    prep_time  INT           NOT NULL CHECK (prep_time >= 0),
    img        VARCHAR2(500),
    flgDeleted NUMBER(1) DEFAULT ON NULL 0 CHECK (flgDeleted IN (0, 1))
);

CREATE SEQUENCE iddish_seq
START WITH 1
INCREMENT BY 1;

CREATE OR REPLACE TRIGGER iddish_trigger
    BEFORE INSERT ON Dish
    FOR EACH ROW
BEGIN
    SELECT iddish_seq.nextval INTO :NEW.id FROM dual;
END;
/

CREATE TABLE NeedIngredient
(
    ingredient VARCHAR2(50) NOT NULL REFERENCES Ingredient,
    dish       INT          NOT NULL REFERENCES Dish,
    CONSTRAINT pk_needingredient PRIMARY KEY (ingredient, dish),
    amount     INT DEFAULT ON NULL 1 CHECK (amount >= 0)
);

CREATE TABLE Address
(
    id          INT PRIMARY KEY,
    postal_code VARCHAR2(6)   NOT NULL CHECK (LENGTH(postal_code) = 6),
    town        VARCHAR2(100) NOT NULL,
    street      VARCHAR2(100) NOT NULL,
    num         VARCHAR2(20)  NOT NULL
);

CREATE SEQUENCE idaddress_seq
    START WITH 1
    INCREMENT BY 1;

CREATE OR REPLACE TRIGGER idaddress_trigger
    BEFORE INSERT ON Address
    FOR EACH ROW
BEGIN
    SELECT idaddress_seq.nextval INTO :NEW.id FROM dual;
END;
/

CREATE TABLE "User"
(
    id              INT PRIMARY KEY,
    login           VARCHAR2(50)  NOT NULL,
    password        VARCHAR2(300) NOT NULL,
    email           VARCHAR2(100),
    name            VARCHAR2(50)  NOT NULL,
    surname         VARCHAR2(100) NOT NULL,
    registered_date TIMESTAMP DEFAULT ON NULL SYSTIMESTAMP,
    flgDeleted      NUMBER(1) DEFAULT ON NULL 0 CHECK (flgDeleted IN (0, 1)),
    address         INT REFERENCES Address
);

CREATE SEQUENCE iduser_seq
    START WITH 1
    INCREMENT BY 1;

CREATE OR REPLACE TRIGGER iduser_trigger
    BEFORE INSERT ON "User"
    FOR EACH ROW
BEGIN
    SELECT iduser_seq.nextval INTO :NEW.id FROM dual;
END;
/

-- login is UNIQUE for non-deleted "User"s
CREATE OR REPLACE TRIGGER userlogin_unique
    BEFORE INSERT OR UPDATE OF login ON "User"
    FOR EACH ROW
DECLARE
    found_logins INT;
BEGIN
    SELECT COUNT(login) INTO found_logins FROM "User" WHERE login = :NEW.login AND flgDeleted = 0;

    IF found_logins > 0 THEN
        raise_application_error(-20000, 'Invalid insert/update: user login should be unique for non-deleted accounts');
    END IF;
END;
/

-- Entry in order. When it's created, we can think of it as "in user's cart".
CREATE TABLE Entry
(
    id        INT PRIMARY KEY,
    customer  INT NOT NULL REFERENCES "User",
    dish      INT NOT NULL REFERENCES Dish,
    amount    INT DEFAULT ON NULL 1 CHECK (amount > 0),
    flgInCart INT DEFAULT ON NULL 1 CHECK (flgInCart IN (0, 1))
);

CREATE SEQUENCE identry_seq
    START WITH 1
    INCREMENT BY 1;

CREATE OR REPLACE TRIGGER identry_trigger
    BEFORE INSERT ON Entry
    FOR EACH ROW
BEGIN
    SELECT identry_seq.nextval INTO :NEW.id FROM dual;
END;
/

-- (customer, dish) is UNIQUE for entries in cart
CREATE OR REPLACE TRIGGER entry_inCart_unique
    BEFORE INSERT OR UPDATE OF customer, dish, flgInCart ON Entry
    FOR EACH ROW
DECLARE
    found_entries INT;
BEGIN
    IF :NEW.flgInCart = 1 THEN
        SELECT COUNT(id) INTO found_entries FROM Entry
        WHERE customer = :NEW.customer AND dish = :NEW.dish AND flgInCart = 1;

        IF found_entries > 0 THEN
            raise_application_error(-20000, 'Invalid insert/update: (customer, dish) should be unique for entries in cart');
        END IF;
    END IF;
END;
/

CREATE TABLE "Order"
(
    id                INT PRIMARY KEY,
    ordered_date      TIMESTAMP DEFAULT ON NULL SYSTIMESTAMP,
    estimated_arrival TIMESTAMP DEFAULT ON NULL SYSTIMESTAMP + INTERVAL '20' MINUTE,
    arrived_at        TIMESTAMP,
    flgActive         NUMBER(1) DEFAULT ON NULL 1 CHECK (flgActive IN (0, 1)),
    address           INT NOT NULL REFERENCES Address
);

CREATE SEQUENCE idorder_seq
    START WITH 1
    INCREMENT BY 1;

CREATE OR REPLACE TRIGGER idorder_trigger
    BEFORE INSERT ON "Order"
    FOR EACH ROW
BEGIN
    SELECT idorder_seq.nextval INTO :NEW.id FROM dual;
END;
/

CREATE TABLE OrderEntries
(
    "order"  INT NOT NULL REFERENCES "Order",
    entry    INT NOT NULL REFERENCES Entry,
    CONSTRAINT pk_orderEntries PRIMARY KEY ("order", entry)
);

--PROCEDURES--

CREATE OR REPLACE PROCEDURE role(user_ IN INT, role_ OUT INT) IS
    cnt INT;
    cnt_address INT;
BEGIN
    SELECT COUNT(login) INTO cnt FROM "User" WHERE id = user_ AND flgDeleted = 0;
    IF cnt = 0 THEN role_ := -1; -- Non-existent
    ELSE
        SELECT COUNT(address) INTO cnt_address FROM "User" WHERE id = user_;
        IF cnt_address = 0 THEN role_ := 1; -- Employee
        ELSE role_ := 0; -- Customer
        END IF;
    END IF;
END;
/

CREATE OR REPLACE PROCEDURE sign_up(
    login_ IN VARCHAR2,
    password_ IN VARCHAR2,
    email_ IN VARCHAR2,
    name_ IN VARCHAR2,
    surname_ IN VARCHAR2,
    address_ IN INT,
    success_ OUT INT
) IS
    counter INT;
BEGIN
    SELECT COUNT(id) INTO counter FROM "User"
    WHERE flgDeleted = 0
      AND login = login_;

    IF counter > 0 THEN
        success_ := -1;
    ELSE
        INSERT INTO "User" VALUES (NULL, login_, password_, email_, name_, surname_, SYSTIMESTAMP, 0, address_);
        COMMIT;
        success_ := 0;
    END IF;
END;
/

CREATE OR REPLACE PROCEDURE add_address(
    postal_code_ IN VARCHAR2,
    town_ IN VARCHAR2,
    street_ IN VARCHAR2,
    num_ IN VARCHAR2,
    address_ OUT NUMBER
) IS
BEGIN
    SELECT id INTO address_ FROM Address
    WHERE postal_code = postal_code_
        AND town = town_
        AND street = street_
        AND num = num_;

    RETURN;

    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            INSERT INTO Address VALUES (NULL, postal_code_, town_, street_, num_);

            SELECT id INTO address_ FROM Address
            WHERE postal_code = postal_code_
              AND town = town_
              AND street = street_
              AND num = num_;

            COMMIT;
END;
/

CREATE OR REPLACE PROCEDURE change_address(customer_ IN INT, address_new IN INT) IS
    address_old INT; -- forcing an exception
BEGIN
    -- check if given customer is not an employee (i.e. if they have some address)
    SELECT address INTO address_old FROM "User" WHERE id = customer_;

    UPDATE "User" SET address = address_new WHERE id = customer_;
    COMMIT;
END;
/

CREATE OR REPLACE PROCEDURE stock_ingredient(name_ IN VARCHAR2, amount_ IN INT) IS
    base_stock INT;
BEGIN
    IF amount_ < 0 THEN
        raise_application_error(-20001, 'Invalid argument: amount_ should be a non-negative integer');
    END IF;

    BEGIN
        SELECT stock INTO base_stock FROM Ingredient WHERE name = name_;

    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            INSERT INTO Ingredient VALUES (name_, 0);
            base_stock := 0;
    END;

    UPDATE Ingredient SET stock = base_stock + amount_ WHERE name = name_;
    COMMIT;
END;
/

CREATE OR REPLACE FUNCTION possible_order(dish_ IN INT) RETURN NUMBER IS
    retval NUMBER := 999999999999999999999999999999999999;
BEGIN
    FOR row IN (
        SELECT I.stock, NI.amount
        FROM Ingredient I JOIN (
            SELECT * FROM NeedIngredient
            WHERE dish = dish_
        ) NI ON I.name = NI.ingredient
    ) LOOP
        retval := LEAST(retval, FLOOR(row.stock / row.amount));
    END LOOP;

    IF retval = 999999999999999999999999999999999999 THEN RETURN 0;
    ELSE RETURN retval;
    END IF;
END;
/

CREATE OR REPLACE PROCEDURE get_possible_order(dish_ IN INT, how_many_ OUT INT) IS
BEGIN
    how_many_ := possible_order(dish_);
END;
/

CREATE OR REPLACE PROCEDURE update_entry(customer_ IN INT, dish_ IN INT, amount_ IN INT) IS
    entry_present INT;
BEGIN
    SELECT COUNT(customer) INTO entry_present FROM Entry
    WHERE customer = customer_ AND dish = dish_ AND flgInCart = 1;

    IF entry_present = 1 THEN
        IF amount_ > 0 THEN
            UPDATE Entry SET amount = amount_ WHERE customer = customer_ AND dish = dish_ AND flgInCart = 1;
        ELSE
            DELETE FROM Entry WHERE customer = customer_ AND dish = dish_ AND flgInCart = 1;
        END IF;
    ELSE
        IF amount_ > 0 THEN
            INSERT INTO Entry VALUES (NULL, customer_, dish_, amount_, 1);
        END IF;
    END IF;
    COMMIT;
END;
/

CREATE OR REPLACE PROCEDURE place_order(customer_ IN INT, success_ OUT INT) IS
    entries INT;
    now TIMESTAMP := SYSTIMESTAMP;
    minutes_to_arrive INT := const.base_arrival;
    order_id INT;
BEGIN
    SELECT COUNT(id) INTO entries FROM Entry WHERE customer = customer_ AND flgInCart = 1;

    IF entries > 0 THEN
        INSERT INTO "Order" VALUES (
            NULL,                                               -- id
            now,                                                -- ordered_at
            now + NUMTODSINTERVAL(minutes_to_arrive, 'MINUTE'), -- estimated_arrival
            NULL,                                               -- arrived_at
            1,                                                  -- flgActive
            (SELECT address FROM "User" WHERE id = customer_)   -- address
        );
        order_id := idorder_seq.currval;

        FOR dish_row IN (
            SELECT E.id entry_id,
                   E.dish,
                   E.amount,
                   D.prep_time,
                   D.price
            FROM Entry E JOIN Dish D ON E.dish = D.id
            WHERE E.customer = customer_ AND E.flgInCart = 1
        ) LOOP

            IF possible_order(dish_row.dish) < dish_row.amount THEN
                ROLLBACK;
                success_ := -1; -- insufficient amount
                RETURN;
            ELSE
                FOR ing_row IN (
                    SELECT NI.ingredient,
                           NI.amount
                    FROM Dish D JOIN NeedIngredient NI ON D.id = NI.dish
                    WHERE D.id = dish_row.dish
                ) LOOP
                    UPDATE Ingredient SET stock = stock - ing_row.amount * dish_row.amount
                    WHERE name = ing_row.ingredient;
                END LOOP;
            END IF;

            minutes_to_arrive := minutes_to_arrive + dish_row.prep_time * dish_row.amount;

            INSERT INTO OrderEntries VALUES (order_id, dish_row.entry_id);
        END LOOP;

        UPDATE "Order" SET estimated_arrival = now + NUMTODSINTERVAL(minutes_to_arrive, 'MINUTE') WHERE id = order_id;
        UPDATE Entry SET flgInCart = 0 WHERE customer = customer_;

        success_ := 1; -- success
        COMMIT;
    ELSE
        success_ := 0; -- nothing to order
    END IF;
END;
/

CREATE OR REPLACE FUNCTION order_price(order_id IN INT) RETURN NUMBER IS
    total_price INT := const.delivery_price;
BEGIN
    FOR row IN (
        SELECT E.amount amount,
               D.price price
        FROM OrderEntries OE JOIN Entry E ON OE.entry = E.id JOIN Dish D ON E.dish = D.id
        WHERE OE."order" = order_id
    ) LOOP
        total_price := total_price + row.amount * row.price;
    END LOOP;
    RETURN total_price;
END;
/

CREATE OR REPLACE PROCEDURE get_order_price(order_id IN INT, price_ OUT INT) IS
BEGIN
    price_ := order_price(order_id);
END;
/

CREATE OR REPLACE PROCEDURE order_status(order_id IN INT, order_status OUT INT) IS
    row "Order"%ROWTYPE;
BEGIN
    SELECT * INTO row FROM "Order" WHERE id = order_id;

    IF row.flgActive = 1 AND row.arrived_at IS NULL THEN
        order_status := 0; -- pending
    ELSIF row.flgActive = 0 AND row.arrived_at IS NOT NULL THEN
        order_status := 1; -- arrived
    ELSIF row.flgActive = 0 AND row.arrived_at IS NULL THEN
        order_status := 2; -- canceled by employee
    ELSE
        raise_application_error(-20002, 'Invalid behaviour: order is active and has been delivered');
        order_status := -1;
    END IF;
END;
/
