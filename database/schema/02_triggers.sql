-- ============================================================
-- 02_triggers.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Triggers de validación de peso y estatura
-- v1.1 - Añadida validación de atleta activo
--      - Añadida validación de estado de competicion
-- v1.2 - Sustituida validación de campo estado
--        por fn_estado_competicion basada en fecha
-- ============================================================

USE gestion_competiciones;

DROP TRIGGER IF EXISTS trg_validar_inscripcion_insert;
DROP TRIGGER IF EXISTS trg_validar_inscripcion_update;

DELIMITER $$

-- ============================================================
-- trg_validar_inscripcion_insert
-- Se dispara ANTES de insertar en inscripcion.
-- Validaciones en orden:
-- 1. Atleta activo (v1.1)
-- 2. Competicion no cerrada usando fn_estado_competicion (v1.2)
--    (sustituye la validacion directa del campo estado)
-- 3. Peso no supera el máximo de la categoría
-- 4. Estatura dentro del rango de la categoría
-- ============================================================
CREATE TRIGGER trg_validar_inscripcion_insert
BEFORE INSERT ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);
  DECLARE v_activo     TINYINT(1);
  DECLARE v_estado     VARCHAR(20);

  -- v1.1: validar que el atleta está activo
  SELECT activo INTO v_activo
    FROM atleta WHERE id_atleta = NEW.id_atleta;

  IF v_activo = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El atleta está inactivo y no puede inscribirse';
  END IF;

  -- v1.2: validar estado de competicion via función
  -- fn_estado_competicion calcula el estado en tiempo real
  -- según la fecha del evento, sin necesidad de campo estado
  SET v_estado = fn_estado_competicion(NEW.id_competicion);

  IF v_estado = 'cerrada' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición está cerrada y no admite nuevas inscripciones';
  END IF;

  -- Validar peso y estatura contra los límites de la categoría
  IF NEW.id_categoria IS NOT NULL THEN

    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria
     WHERE id_categoria = NEW.id_categoria;

    IF NEW.peso_registro IS NOT NULL AND v_peso_max IS NOT NULL
       AND NEW.peso_registro > v_peso_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El peso del atleta supera el máximo permitido para la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_min IS NOT NULL
       AND NEW.estatura_registro < v_altura_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta es inferior al mínimo de la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_max IS NOT NULL
       AND NEW.estatura_registro > v_altura_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta supera el máximo de la categoría';
    END IF;

  END IF;
END$$

-- ============================================================
-- trg_validar_inscripcion_update
-- Se dispara ANTES de actualizar en inscripcion.
-- Aplica las mismas validaciones de peso y estatura
-- para cuando se corrigen datos de una inscripción existente.
-- ============================================================
CREATE TRIGGER trg_validar_inscripcion_update
BEFORE UPDATE ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);

  IF NEW.id_categoria IS NOT NULL THEN

    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria
     WHERE id_categoria = NEW.id_categoria;

    IF NEW.peso_registro IS NOT NULL AND v_peso_max IS NOT NULL
       AND NEW.peso_registro > v_peso_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El peso del atleta supera el máximo permitido para la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_min IS NOT NULL
       AND NEW.estatura_registro < v_altura_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta es inferior al mínimo de la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_max IS NOT NULL
       AND NEW.estatura_registro > v_altura_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta supera el máximo de la categoría';
    END IF;

  END IF;
END$$

DELIMITER ;
