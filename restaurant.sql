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

VARIABLE base_arrival NUMBER;
EXECUTE :base_arrival := 20;

VARIABLE delivery_price NUMBER;
EXECUTE :delivery_price := 10;

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
    SELECT COUNT(id) INTO found_entries FROM Entry
    WHERE customer = :NEW.customer AND dish = :NEW.dish AND flgInCart = 1;

    IF found_entries > 0 THEN
        raise_application_error(-20000, 'Invalid insert/update: (customer, dish) should be unique for entries in cart');
    END IF;
END;
/

CREATE TABLE "Order"
(
    id                INT PRIMARY KEY,
    ordered_date      TIMESTAMP DEFAULT ON NULL SYSTIMESTAMP,
    estimated_arrival TIMESTAMP DEFAULT ON NULL SYSTIMESTAMP + NUMTODSINTERVAL(:base_arrival, 'MINUTE'),
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

CREATE OR REPLACE FUNCTION sign_in(login_ IN VARCHAR2, password_ IN VARCHAR2) RETURN NUMBER IS
    retval NUMBER;
BEGIN
    SELECT id INTO retval
    FROM "User"
    WHERE flgDeleted = 0
        AND login = login_
        AND password = password_;
    RETURN retval;

    EXCEPTION
        WHEN NO_DATA_FOUND THEN RETURN -1;
END;
/

CREATE OR REPLACE FUNCTION sign_up(
    login_ IN VARCHAR2,
    password_ IN VARCHAR2,
    email_ IN VARCHAR2,
    name_ IN VARCHAR2,
    surname_ IN VARCHAR2,
    address_ IN INT
) RETURN NUMBER IS
    retval NUMBER;
    counter INT;
BEGIN
    SELECT COUNT(id) INTO counter FROM "User"
    WHERE flgDeleted = 0
      AND login = login_;

    IF counter > 0 THEN
        RETURN -1;
    ELSE
        INSERT INTO "User" VALUES (NULL, login_, password_, email_, name_, surname_, SYSTIMESTAMP, 0, address_);
            retval = iduser_seq.currval;
        COMMIT;
        RETURN retval;
    END IF;
END;
/

CREATE OR REPLACE FUNCTION add_address(
    postal_code_ IN VARCHAR2,
    town_ IN VARCHAR2,
    street_ IN VARCHAR2,
    num_ IN VARCHAR2
) RETURN NUMBER IS
    retval NUMBER;
BEGIN
    SELECT id INTO retval FROM Address
    WHERE postal_code = postal_code_
        AND town = town_
        AND street = street_
        AND num = num_;

    RETURN retval;

    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            INSERT INTO Address VALUES (NULL, postal_code_, town_, street_, num_);
            retval = idaddress_seq.currval;
            COMMIT;
            RETURN retval;
END;
/

CREATE OR REPLACE PROCEDURE change_address(customer_ IN INT, address_ IN INT) IS
BEGIN
    -- check if given customer is not an employee (i.e. if they have some address)

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
    retval NUMBER;
BEGIN
    FOR row IN (
        SELECT I.stock, NI.amount FROM Ingredient I JOIN NeedIngredient NI ON I.name = NI.ingredient
    ) LOOP
        retval = LEAST(retval, FLOOR(row.stock / row.amount));
    END LOOP;
    RETURN retval;
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

CREATE OR REPLACE FUNCTION place_order(customer_ IN INT) RETURN NUMBER IS
    entries INT;
    now TIMESTAMP := SYSTIMESTAMP;
    minutes_to_arrive INT := :base_arrival;
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
        order_id = idorder_seq.currval;

        FOR row IN (
            SELECT E.id entry_id,
                   E.dish,
                   E.amount,
                   D.prep_time,
                   D.price
            FROM Entry E JOIN Dish D ON E.dish = D.id
            WHERE E.customer = customer_ AND E.flgInCart = 1
        ) LOOP

            IF possible_order(row.dish) < row.amount THEN
                ROLLBACK;
                RETURN -1; -- insufficient amount
            END IF;

            minutes_to_arrive = minutes_to_arrive + row.prep_time * row.amount;

            INSERT INTO OrderEntries VALUES (order_id, row.entry_id);
        END LOOP;

        UPDATE "Order" SET estimated_arrival = now + NUMTODSINTERVAL(minutes_to_arrive, 'MINUTE') WHERE id = order_id;
        UPDATE Entry SET flgInCart = 0 WHERE customer = customer_;
        COMMIT;
        RETURN 1; -- success
    END IF;
    RETURN 0; -- nothing to order
END;
/

CREATE OR REPLACE FUNCTION order_price(order_id IN INT) RETURN NUMBER IS
    total_price INT := :delivery_price;
BEGIN
    FOR row IN (
        SELECT E.amount amount,
               D.price price
        FROM OrderEntries OE JOIN Entry E ON OE.entry = E.id JOIN Dish D ON E.dish = D.id
        WHERE OE."order" = order_id
    ) LOOP
        total_price = total_price + row.amount * row.price;
    END LOOP;
    RETURN total_price;
END;
/
