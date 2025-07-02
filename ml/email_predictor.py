#!/usr/bin/env python3
"""
ROTZ Email Butler - Advanced Machine Learning Email Predictor
Implements sophisticated ML models for email behavior prediction and optimization
"""

import os
import sys
import json
import numpy as np
import pandas as pd
import pickle
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional, Any
import warnings
warnings.filterwarnings('ignore')

# ML Libraries
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.neural_network import MLPClassifier
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.model_selection import train_test_split, cross_val_score, GridSearchCV
from sklearn.metrics import classification_report, confusion_matrix, accuracy_score
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.pipeline import Pipeline
import joblib

# Deep Learning
try:
    import tensorflow as tf
    from tensorflow.keras.models import Sequential, Model
    from tensorflow.keras.layers import Dense, LSTM, Embedding, Dropout, Attention
    from tensorflow.keras.preprocessing.text import Tokenizer
    from tensorflow.keras.preprocessing.sequence import pad_sequences
    TENSORFLOW_AVAILABLE = True
except ImportError:
    TENSORFLOW_AVAILABLE = False

# NLP Libraries
try:
    import spacy
    import nltk
    from transformers import pipeline, AutoTokenizer, AutoModel
    NLP_AVAILABLE = True
except ImportError:
    NLP_AVAILABLE = False

# Database
import mysql.connector
import redis

class EmailPredictor:
    """Advanced ML-powered email behavior prediction system"""
    
    def __init__(self, config_path: str = None):
        """Initialize the email predictor with configuration"""
        self.config = self._load_config(config_path)
        self.logger = self._setup_logging()
        
        # Database connections
        self.db = self._connect_database()
        self.redis = self._connect_redis()
        
        # ML Models
        self.models = {}
        self.vectorizers = {}
        self.scalers = {}
        self.label_encoders = {}
        
        # Model paths
        self.model_dir = self.config.get('model_dir', '/var/www/html/ml/models')
        os.makedirs(self.model_dir, exist_ok=True)
        
        # Features
        self.feature_columns = [
            'hour_of_day', 'day_of_week', 'month', 'email_length',
            'subject_length', 'sender_frequency', 'recipient_count',
            'attachment_count', 'has_links', 'urgency_keywords',
            'sentiment_score', 'readability_score', 'spam_score'
        ]
        
        self.logger.info("EmailPredictor initialized successfully")
    
    def _load_config(self, config_path: str) -> Dict:
        """Load configuration from file or environment"""
        if config_path and os.path.exists(config_path):
            with open(config_path, 'r') as f:
                return json.load(f)
        
        return {
            'db_host': os.getenv('DB_HOST', 'localhost'),
            'db_port': int(os.getenv('DB_PORT', 3306)),
            'db_name': os.getenv('DB_NAME', 'rotz_email_butler'),
            'db_user': os.getenv('DB_USER', 'root'),
            'db_password': os.getenv('DB_PASSWORD', ''),
            'redis_host': os.getenv('REDIS_HOST', 'localhost'),
            'redis_port': int(os.getenv('REDIS_PORT', 6379)),
            'model_update_interval': 3600,  # 1 hour
            'min_training_samples': 1000,
            'feature_importance_threshold': 0.01,
        }
    
    def _setup_logging(self) -> logging.Logger:
        """Setup logging configuration"""
        logger = logging.getLogger('EmailPredictor')
        logger.setLevel(logging.INFO)
        
        if not logger.handlers:
            handler = logging.StreamHandler()
            formatter = logging.Formatter(
                '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
            )
            handler.setFormatter(formatter)
            logger.addHandler(handler)
        
        return logger
    
    def _connect_database(self) -> mysql.connector.MySQLConnection:
        """Connect to MySQL database"""
        try:
            connection = mysql.connector.connect(
                host=self.config['db_host'],
                port=self.config['db_port'],
                database=self.config['db_name'],
                user=self.config['db_user'],
                password=self.config['db_password'],
                autocommit=True
            )
            self.logger.info("Database connection established")
            return connection
        except Exception as e:
            self.logger.error(f"Database connection failed: {e}")
            raise
    
    def _connect_redis(self) -> redis.Redis:
        """Connect to Redis cache"""
        try:
            r = redis.Redis(
                host=self.config['redis_host'],
                port=self.config['redis_port'],
                decode_responses=True
            )
            r.ping()
            self.logger.info("Redis connection established")
            return r
        except Exception as e:
            self.logger.error(f"Redis connection failed: {e}")
            return None
    
    def extract_features(self, email_data: Dict) -> Dict:
        """Extract comprehensive features from email data"""
        features = {}
        
        # Temporal features
        timestamp = datetime.fromisoformat(email_data.get('received_at', datetime.now().isoformat()))
        features['hour_of_day'] = timestamp.hour
        features['day_of_week'] = timestamp.weekday()
        features['month'] = timestamp.month
        
        # Content features
        subject = email_data.get('subject', '')
        body = email_data.get('body', '')
        
        features['email_length'] = len(body)
        features['subject_length'] = len(subject)
        features['recipient_count'] = len(email_data.get('recipients', []))
        features['attachment_count'] = len(email_data.get('attachments', []))
        features['has_links'] = 1 if 'http' in body.lower() else 0
        
        # Sender features
        sender = email_data.get('sender', '')
        features['sender_frequency'] = self._get_sender_frequency(sender)
        
        # Content analysis
        features['urgency_keywords'] = self._count_urgency_keywords(subject + ' ' + body)
        features['sentiment_score'] = self._analyze_sentiment(body)
        features['readability_score'] = self._calculate_readability(body)
        features['spam_score'] = self._calculate_spam_score(email_data)
        
        # Advanced NLP features
        if NLP_AVAILABLE:
            features.update(self._extract_nlp_features(subject, body))
        
        return features
    
    def _get_sender_frequency(self, sender: str) -> int:
        """Get frequency of emails from this sender"""
        try:
            cursor = self.db.cursor()
            cursor.execute(
                "SELECT COUNT(*) FROM emails WHERE sender = %s AND received_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                (sender,)
            )
            result = cursor.fetchone()
            return result[0] if result else 0
        except Exception as e:
            self.logger.error(f"Error getting sender frequency: {e}")
            return 0
    
    def _count_urgency_keywords(self, text: str) -> int:
        """Count urgency keywords in text"""
        urgency_keywords = [
            'urgent', 'asap', 'immediate', 'emergency', 'critical',
            'deadline', 'rush', 'priority', 'important', 'action required'
        ]
        text_lower = text.lower()
        return sum(1 for keyword in urgency_keywords if keyword in text_lower)
    
    def _analyze_sentiment(self, text: str) -> float:
        """Analyze sentiment of email content"""
        if not NLP_AVAILABLE:
            return 0.0
        
        try:
            # Use cached sentiment analyzer
            cache_key = f"sentiment:{hash(text)}"
            cached_result = self.redis.get(cache_key) if self.redis else None
            
            if cached_result:
                return float(cached_result)
            
            # Simple sentiment analysis (can be enhanced with transformers)
            positive_words = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic']
            negative_words = ['bad', 'terrible', 'awful', 'horrible', 'disappointing', 'frustrated']
            
            text_lower = text.lower()
            positive_count = sum(1 for word in positive_words if word in text_lower)
            negative_count = sum(1 for word in negative_words if word in text_lower)
            
            sentiment = (positive_count - negative_count) / max(len(text.split()), 1)
            
            if self.redis:
                self.redis.setex(cache_key, 3600, sentiment)  # Cache for 1 hour
            
            return sentiment
        except Exception as e:
            self.logger.error(f"Error analyzing sentiment: {e}")
            return 0.0
    
    def _calculate_readability(self, text: str) -> float:
        """Calculate readability score (Flesch Reading Ease approximation)"""
        if not text:
            return 0.0
        
        sentences = text.count('.') + text.count('!') + text.count('?')
        words = len(text.split())
        syllables = sum(self._count_syllables(word) for word in text.split())
        
        if sentences == 0 or words == 0:
            return 0.0
        
        # Simplified Flesch Reading Ease
        score = 206.835 - (1.015 * (words / sentences)) - (84.6 * (syllables / words))
        return max(0, min(100, score)) / 100  # Normalize to 0-1
    
    def _count_syllables(self, word: str) -> int:
        """Count syllables in a word (approximation)"""
        word = word.lower()
        vowels = 'aeiouy'
        syllable_count = 0
        previous_was_vowel = False
        
        for char in word:
            is_vowel = char in vowels
            if is_vowel and not previous_was_vowel:
                syllable_count += 1
            previous_was_vowel = is_vowel
        
        if word.endswith('e'):
            syllable_count -= 1
        
        return max(1, syllable_count)
    
    def _calculate_spam_score(self, email_data: Dict) -> float:
        """Calculate spam probability score"""
        spam_indicators = 0
        total_checks = 0
        
        subject = email_data.get('subject', '').lower()
        body = email_data.get('body', '').lower()
        sender = email_data.get('sender', '').lower()
        
        # Check for spam keywords
        spam_keywords = [
            'free', 'win', 'winner', 'congratulations', 'prize',
            'money', 'cash', 'loan', 'credit', 'debt', 'viagra',
            'pharmacy', 'casino', 'gambling', 'lottery'
        ]
        
        for keyword in spam_keywords:
            total_checks += 1
            if keyword in subject or keyword in body:
                spam_indicators += 1
        
        # Check for excessive capitalization
        total_checks += 1
        if sum(1 for c in subject if c.isupper()) > len(subject) * 0.5:
            spam_indicators += 1
        
        # Check for suspicious sender patterns
        total_checks += 1
        if any(char.isdigit() for char in sender.split('@')[0]) and len(sender.split('@')[0]) > 10:
            spam_indicators += 1
        
        # Check for excessive exclamation marks
        total_checks += 1
        if (subject + body).count('!') > 3:
            spam_indicators += 1
        
        return spam_indicators / total_checks if total_checks > 0 else 0.0
    
    def _extract_nlp_features(self, subject: str, body: str) -> Dict:
        """Extract advanced NLP features"""
        features = {}
        
        try:
            # Named entity recognition
            text = subject + ' ' + body
            
            # Simple entity counting (can be enhanced with spaCy)
            features['email_count'] = text.count('@')
            features['phone_count'] = len([word for word in text.split() if word.replace('-', '').replace('(', '').replace(')', '').isdigit() and len(word) >= 10])
            features['url_count'] = text.lower().count('http')
            features['money_mentions'] = text.lower().count('$') + text.lower().count('dollar') + text.lower().count('price')
            
            # Text complexity
            unique_words = len(set(text.lower().split()))
            total_words = len(text.split())
            features['vocabulary_richness'] = unique_words / total_words if total_words > 0 else 0
            
        except Exception as e:
            self.logger.error(f"Error extracting NLP features: {e}")
            features = {
                'email_count': 0, 'phone_count': 0, 'url_count': 0,
                'money_mentions': 0, 'vocabulary_richness': 0
            }
        
        return features
    
    def prepare_training_data(self, user_id: Optional[int] = None) -> Tuple[pd.DataFrame, pd.Series]:
        """Prepare training data from database"""
        try:
            # Build query
            query = """
                SELECT 
                    e.*,
                    u.timezone,
                    ea.provider as email_provider,
                    CASE 
                        WHEN e.is_read = 1 THEN 'read'
                        WHEN e.is_archived = 1 THEN 'archived'
                        WHEN e.is_deleted = 1 THEN 'deleted'
                        WHEN e.priority = 'high' THEN 'priority'
                        ELSE 'normal'
                    END as action_taken
                FROM emails e
                JOIN email_accounts ea ON e.email_account_id = ea.id
                JOIN users u ON ea.user_id = u.id
                WHERE e.received_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            """
            
            params = []
            if user_id:
                query += " AND u.id = %s"
                params.append(user_id)
            
            query += " ORDER BY e.received_at DESC LIMIT 10000"
            
            cursor = self.db.cursor(dictionary=True)
            cursor.execute(query, params)
            emails = cursor.fetchall()
            
            if len(emails) < self.config['min_training_samples']:
                self.logger.warning(f"Insufficient training data: {len(emails)} samples")
                return None, None
            
            # Extract features for each email
            features_list = []
            labels = []
            
            for email in emails:
                try:
                    features = self.extract_features(email)
                    features_list.append(features)
                    labels.append(email['action_taken'])
                except Exception as e:
                    self.logger.error(f"Error processing email {email.get('id')}: {e}")
                    continue
            
            # Convert to DataFrame
            df_features = pd.DataFrame(features_list)
            df_labels = pd.Series(labels)
            
            # Handle missing values
            df_features = df_features.fillna(0)
            
            self.logger.info(f"Prepared training data: {len(df_features)} samples, {len(df_features.columns)} features")
            return df_features, df_labels
            
        except Exception as e:
            self.logger.error(f"Error preparing training data: {e}")
            return None, None
    
    def train_models(self, user_id: Optional[int] = None) -> Dict[str, float]:
        """Train multiple ML models and select the best one"""
        self.logger.info(f"Starting model training for user {user_id or 'global'}")
        
        # Prepare data
        X, y = self.prepare_training_data(user_id)
        if X is None or y is None:
            return {}
        
        # Split data
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
        
        # Scale features
        scaler = StandardScaler()
        X_train_scaled = scaler.fit_transform(X_train)
        X_test_scaled = scaler.transform(X_test)
        
        # Encode labels
        label_encoder = LabelEncoder()
        y_train_encoded = label_encoder.fit_transform(y_train)
        y_test_encoded = label_encoder.transform(y_test)
        
        # Define models to train
        models_to_train = {
            'random_forest': RandomForestClassifier(
                n_estimators=100, random_state=42, n_jobs=-1
            ),
            'gradient_boosting': GradientBoostingClassifier(
                n_estimators=100, random_state=42
            ),
            'logistic_regression': LogisticRegression(
                random_state=42, max_iter=1000
            ),
            'neural_network': MLPClassifier(
                hidden_layer_sizes=(100, 50), random_state=42, max_iter=500
            )
        }
        
        # Train and evaluate models
        model_scores = {}
        best_model = None
        best_score = 0
        
        for model_name, model in models_to_train.items():
            try:
                self.logger.info(f"Training {model_name}...")
                
                # Train model
                if model_name in ['logistic_regression', 'neural_network']:
                    model.fit(X_train_scaled, y_train_encoded)
                    y_pred = model.predict(X_test_scaled)
                else:
                    model.fit(X_train, y_train_encoded)
                    y_pred = model.predict(X_test)
                
                # Evaluate
                accuracy = accuracy_score(y_test_encoded, y_pred)
                model_scores[model_name] = accuracy
                
                self.logger.info(f"{model_name} accuracy: {accuracy:.4f}")
                
                # Save best model
                if accuracy > best_score:
                    best_score = accuracy
                    best_model = model_name
                    
                    # Save model, scaler, and encoder
                    model_key = f"user_{user_id}" if user_id else "global"
                    
                    self.models[model_key] = model
                    self.scalers[model_key] = scaler if model_name in ['logistic_regression', 'neural_network'] else None
                    self.label_encoders[model_key] = label_encoder
                    
                    # Save to disk
                    model_path = os.path.join(self.model_dir, f"{model_key}_{model_name}.joblib")
                    joblib.dump({
                        'model': model,
                        'scaler': scaler,
                        'label_encoder': label_encoder,
                        'feature_columns': list(X.columns),
                        'model_type': model_name,
                        'accuracy': accuracy,
                        'trained_at': datetime.now().isoformat()
                    }, model_path)
                    
            except Exception as e:
                self.logger.error(f"Error training {model_name}: {e}")
                model_scores[model_name] = 0.0
        
        self.logger.info(f"Best model: {best_model} with accuracy: {best_score:.4f}")
        
        # Update model metadata in database
        self._update_model_metadata(user_id, best_model, best_score, model_scores)
        
        return model_scores
    
    def predict_email_action(self, email_data: Dict, user_id: Optional[int] = None) -> Dict:
        """Predict the best action for an email"""
        try:
            # Get model
            model_key = f"user_{user_id}" if user_id else "global"
            
            if model_key not in self.models:
                # Try to load from disk
                self._load_model(model_key)
            
            if model_key not in self.models:
                # Fallback to global model
                if "global" not in self.models:
                    self._load_model("global")
                model_key = "global"
            
            if model_key not in self.models:
                return {
                    'predicted_action': 'normal',
                    'confidence': 0.0,
                    'error': 'No trained model available'
                }
            
            # Extract features
            features = self.extract_features(email_data)
            
            # Prepare feature vector
            feature_df = pd.DataFrame([features])
            
            # Ensure all required columns are present
            for col in self.feature_columns:
                if col not in feature_df.columns:
                    feature_df[col] = 0
            
            feature_df = feature_df[self.feature_columns]
            
            # Scale if needed
            if self.scalers.get(model_key):
                feature_vector = self.scalers[model_key].transform(feature_df)
            else:
                feature_vector = feature_df.values
            
            # Predict
            model = self.models[model_key]
            prediction = model.predict(feature_vector)[0]
            probabilities = model.predict_proba(feature_vector)[0]
            
            # Decode prediction
            predicted_action = self.label_encoders[model_key].inverse_transform([prediction])[0]
            confidence = max(probabilities)
            
            # Get feature importance (for tree-based models)
            feature_importance = {}
            if hasattr(model, 'feature_importances_'):
                for i, importance in enumerate(model.feature_importances_):
                    if importance > self.config['feature_importance_threshold']:
                        feature_importance[self.feature_columns[i]] = importance
            
            return {
                'predicted_action': predicted_action,
                'confidence': float(confidence),
                'probabilities': {
                    self.label_encoders[model_key].inverse_transform([i])[0]: float(prob)
                    for i, prob in enumerate(probabilities)
                },
                'feature_importance': feature_importance,
                'model_used': model_key
            }
            
        except Exception as e:
            self.logger.error(f"Error predicting email action: {e}")
            return {
                'predicted_action': 'normal',
                'confidence': 0.0,
                'error': str(e)
            }
    
    def _load_model(self, model_key: str) -> bool:
        """Load model from disk"""
        try:
            # Find the latest model file
            model_files = [f for f in os.listdir(self.model_dir) if f.startswith(f"{model_key}_")]
            if not model_files:
                return False
            
            # Load the most recent model
            latest_model = max(model_files, key=lambda x: os.path.getctime(os.path.join(self.model_dir, x)))
            model_path = os.path.join(self.model_dir, latest_model)
            
            model_data = joblib.load(model_path)
            
            self.models[model_key] = model_data['model']
            self.scalers[model_key] = model_data.get('scaler')
            self.label_encoders[model_key] = model_data['label_encoder']
            
            self.logger.info(f"Loaded model {model_key} from {model_path}")
            return True
            
        except Exception as e:
            self.logger.error(f"Error loading model {model_key}: {e}")
            return False
    
    def _update_model_metadata(self, user_id: Optional[int], best_model: str, 
                              best_score: float, all_scores: Dict[str, float]):
        """Update model metadata in database"""
        try:
            cursor = self.db.cursor()
            
            # Insert or update model metadata
            query = """
                INSERT INTO ml_models (user_id, model_type, accuracy, scores, trained_at, is_active)
                VALUES (%s, %s, %s, %s, NOW(), 1)
                ON DUPLICATE KEY UPDATE
                model_type = VALUES(model_type),
                accuracy = VALUES(accuracy),
                scores = VALUES(scores),
                trained_at = VALUES(trained_at),
                is_active = VALUES(is_active)
            """
            
            cursor.execute(query, (
                user_id,
                best_model,
                best_score,
                json.dumps(all_scores)
            ))
            
            self.logger.info(f"Updated model metadata for user {user_id}")
            
        except Exception as e:
            self.logger.error(f"Error updating model metadata: {e}")
    
    def get_model_performance(self, user_id: Optional[int] = None) -> Dict:
        """Get model performance metrics"""
        try:
            cursor = self.db.cursor(dictionary=True)
            
            query = """
                SELECT model_type, accuracy, scores, trained_at
                FROM ml_models
                WHERE user_id = %s AND is_active = 1
                ORDER BY trained_at DESC
                LIMIT 1
            """
            
            cursor.execute(query, (user_id,))
            result = cursor.fetchone()
            
            if result:
                result['scores'] = json.loads(result['scores'])
                return result
            else:
                return {'error': 'No model found'}
                
        except Exception as e:
            self.logger.error(f"Error getting model performance: {e}")
            return {'error': str(e)}
    
    def retrain_if_needed(self, user_id: Optional[int] = None) -> bool:
        """Check if model needs retraining and retrain if necessary"""
        try:
            # Check last training time
            cursor = self.db.cursor()
            cursor.execute(
                "SELECT trained_at FROM ml_models WHERE user_id = %s ORDER BY trained_at DESC LIMIT 1",
                (user_id,)
            )
            result = cursor.fetchone()
            
            if result:
                last_trained = result[0]
                time_since_training = datetime.now() - last_trained
                
                if time_since_training.total_seconds() < self.config['model_update_interval']:
                    return False  # No need to retrain yet
            
            # Check if we have enough new data
            cursor.execute(
                "SELECT COUNT(*) FROM emails WHERE user_id = %s AND received_at > %s",
                (user_id, result[0] if result else datetime.now() - timedelta(days=30))
            )
            new_emails = cursor.fetchone()[0]
            
            if new_emails < 100:  # Need at least 100 new emails
                return False
            
            # Retrain model
            self.logger.info(f"Retraining model for user {user_id} with {new_emails} new emails")
            scores = self.train_models(user_id)
            
            return len(scores) > 0
            
        except Exception as e:
            self.logger.error(f"Error checking retrain status: {e}")
            return False

def main():
    """Main function for command-line usage"""
    import argparse
    
    parser = argparse.ArgumentParser(description='ROTZ Email Butler ML Predictor')
    parser.add_argument('--action', choices=['train', 'predict', 'evaluate'], required=True)
    parser.add_argument('--user-id', type=int, help='User ID for personalized models')
    parser.add_argument('--email-id', type=int, help='Email ID for prediction')
    parser.add_argument('--config', help='Configuration file path')
    
    args = parser.parse_args()
    
    # Initialize predictor
    predictor = EmailPredictor(args.config)
    
    if args.action == 'train':
        scores = predictor.train_models(args.user_id)
        print(f"Training completed. Scores: {scores}")
        
    elif args.action == 'predict' and args.email_id:
        # Get email data from database
        cursor = predictor.db.cursor(dictionary=True)
        cursor.execute("SELECT * FROM emails WHERE id = %s", (args.email_id,))
        email_data = cursor.fetchone()
        
        if email_data:
            prediction = predictor.predict_email_action(email_data, args.user_id)
            print(f"Prediction: {json.dumps(prediction, indent=2)}")
        else:
            print(f"Email {args.email_id} not found")
            
    elif args.action == 'evaluate':
        performance = predictor.get_model_performance(args.user_id)
        print(f"Model Performance: {json.dumps(performance, indent=2, default=str)}")

if __name__ == '__main__':
    main()

